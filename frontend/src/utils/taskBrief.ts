import type { BriefMention, BriefInterpretation, BriefIssue, TaskBriefForm, DraftField } from '../types/taskBrief'

const OFFSET = 8 * 60 * 60 * 1000
const dayNames: Record<string, number> = { sunday: 0, linggo: 0, monday: 1, lunes: 1, tuesday: 2, martes: 2, wednesday: 3, miyerkules: 3, thursday: 4, huwebes: 4, friday: 5, biyernes: 5, saturday: 6, sabado: 6 }
const months = ['january','february','march','april','may','june','july','august','september','october','november','december']
const calendarPattern = /\b(day after tomorrow|makalawa|today|ngayon|tomorrow|bukas|monday|tuesday|wednesday|thursday|friday|saturday|sunday|lunes|martes|miyerkules|huwebes|biyernes|sabado|linggo)\b|\b(january|february|march|april|may|june|july|august|september|october|november|december)\s+(\d{1,2})(?:st|nd|rd|th)?(?:,?\s+(\d{4}))?\b|\b(\d{4})-(\d{2})-(\d{2})\b/gi
const dateWords = new RegExp(calendarPattern.source, 'i')

export function validMentions(text: string, mentions: BriefMention[]): BriefMention[] {
  return mentions.filter(m => Number.isInteger(m.start) && Number.isInteger(m.end) && m.start >= 0 && m.end > m.start && text.slice(m.start,m.end) === '@' + m.label)
}

// Native textarea offsets are UTF-16. Preserve only tokens untouched by an edit.
export function reconcileMentions(before: string, after: string, mentions: BriefMention[]): BriefMention[] {
  let start = 0, suffix = 0
  while (start < before.length && start < after.length && before[start] === after[start]) start++
  while (suffix < before.length - start && suffix < after.length - start && before[before.length - 1 - suffix] === after[after.length - 1 - suffix]) suffix++
  const oldEnd = before.length - suffix, delta = after.length - before.length
  return validMentions(after, mentions.flatMap(m => m.end <= start ? [{...m}] : m.start >= oldEnd ? [{...m,start:m.start+delta,end:m.end+delta}] : []))
}

export function activeMention(text: string, caret: number): { start: number; end: number; query: string } | null {
  const start = text.lastIndexOf('@',caret-1)
  if (start < 0 || start >= caret || start > 0 && !/[\s([{]/.test(text[start-1]!)) return null
  const query = text.slice(start+1,caret)
  if (query.length > 70 || /[\n@,;.!?]/.test(query)) return null
  return {start,end:caret,query}
}

export function mergeBriefFields(current: TaskBriefForm, incoming: TaskBriefForm, edited: Partial<Record<DraftField, boolean>>): TaskBriefForm {
  const next = {...current,assignee_ids:[...current.assignee_ids]}
  for (const field of Object.keys(incoming) as DraftField[]) {
    if (!edited[field]) (next as Record<DraftField, unknown>)[field] = Array.isArray(incoming[field]) ? [...incoming[field]] : incoming[field]
  }
  return next
}

export function retainBriefSafeguards(local: BriefInterpretation, proposed: BriefInterpretation): BriefInterpretation {
  const protectedIssues=local.issues.filter(issue=>['brief','mentions','team_id','assignee_ids'].includes(issue.field))
  const issues=[...protectedIssues,...proposed.issues].filter((issue,index,all)=>all.findIndex(other=>other.field===issue.field&&other.message===issue.message)===index)
  return {...proposed,ownership:proposed.ownership==='team'&&local.ownership!=='team'?'unresolved':proposed.ownership,issues}
}

function dateString(date: Date) { return date.toISOString().slice(0,10) }
function localReference(reference: Date) { return new Date(reference.getTime()+OFFSET) }
function dateAt(year: number, month: number, day: number): string | null {
  const date = new Date(Date.UTC(year,month,day))
  return date.getUTCFullYear() === year && date.getUTCMonth() === month && date.getUTCDate() === day ? dateString(date) : null
}

function calendarDate(phrase: string, reference: Date): {day:string|null;issue?:string} {
  if(/\b\d{1,2}[/-]\d{1,2}(?:[/-]\d{2,4})?\b/.test(phrase.replace(/\b\d{4}-\d{2}-\d{2}\b/g,'')))return {day:null,issue:'Write the date as YYYY-MM-DD or a month name and day to avoid an ambiguous numeric date.'}
  if(/\b(?:next\s+(?:week|month|year)|(?:in|after)\s+\d+\s+(?:days?|weeks?|months?|hours?)|susunod\s+(?:na\s+)?(?:linggo|buwan))\b/i.test(phrase))return {day:null,issue:'Choose an exact calendar date in the preview for this timing phrase.'}
  const days: (string|null)[]=[]
  for (const match of phrase.toLowerCase().matchAll(calendarPattern)) {
    const local=localReference(reference), word=match[1]
    if (match[5]) days.push(dateAt(Number(match[5]),Number(match[6])-1,Number(match[7])))
    else if (match[2]) days.push(dateAt(Number(match[4]||local.getUTCFullYear()),months.indexOf(match[2]),Number(match[3])))
    else if (word) {
      if (word==='makalawa'||word==='day after tomorrow') local.setUTCDate(local.getUTCDate()+2)
      else if (word==='bukas'||word==='tomorrow') local.setUTCDate(local.getUTCDate()+1)
      else if (word in dayNames) { let delta=(dayNames[word]! - local.getUTCDay()+7)%7; if(delta===0&&/\bnext\s*$/i.test(phrase.slice(0,match.index)))delta=7;local.setUTCDate(local.getUTCDate()+delta) }
      days.push(dateString(local))
    }
  }
  if (days.includes(null)) return {day:null,issue:'Choose a valid calendar date in the preview.'}
  if (new Set(days).size>1) return {day:null,issue:'This timing phrase has conflicting dates. Choose one exact date in the preview.'}
  return {day:days[0]||null}
}

export function parseBriefDate(phrase: string, reference: Date, field: 'deadline' | 'scheduled_at', contextDay?: string): {value:string;issue?:string;dateOnly:boolean} {
  const text=phrase.trim().toLowerCase(), calendar=calendarDate(text,reference)
  if(calendar.issue)return {value:'',issue:calendar.issue,dateOnly:false}
  const day=calendar.day||contextDay
  if (!day) return {value:'',issue:'Choose an exact date in the preview.',dateOnly:false}
  if (/\b(eod|end of day|mamaya|later)\b/.test(text)) return {value:'',issue:'Choose an exact time in the preview.',dateOnly:false}
  const explicitClocks=[...text.matchAll(/\b\d{1,2}(?::\d{2})?\s*(?:am|pm)\b|\b\d{1,2}:\d{2}\b(?!\s*(?:am|pm)\b)/g)]
  if(explicitClocks.length>1)return {value:'',issue:'This timing phrase has multiple times. Choose one exact time in the preview.',dateOnly:false}
  const clock = text.match(/\b(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b/)
    || text.match(/(?:at|alas|@)\s*(\d{1,2})(?::(\d{2}))?\b/)
    || text.match(/\b(\d{1,2}):(\d{2})\b/)
  if (clock) {
    let hour=Number(clock[1]);const minutes=Number(clock[2] || 0), meridian=clock[3]?.toLowerCase()||(/\b(?:gabi|hapon|evening|afternoon)\b/.test(text)?'pm':/\b(?:umaga|morning)\b/.test(text)?'am':undefined)
    if (minutes>59 || hour>23 || meridian && (hour<1 || hour>12)) return {value:'',issue:'Choose a valid time in the preview.',dateOnly:false}
    if (!meridian && !clock[2]) return {value:'',issue:'Specify AM or PM, or choose the time in the preview.',dateOnly:false}
    if (meridian) hour=hour%12+(meridian==='pm'?12:0)
    return {value:`${day}T${String(hour).padStart(2,'0')}:${String(minutes).padStart(2,'0')}`,dateOnly:false}
  }
  if (/\bnoon\b/.test(text)) return {value:day+'T12:00',dateOnly:false}
  if (/\bmidnight\b/.test(text)) return {value:day+'T00:00',dateOnly:false}
  if (/\b(afternoon|morning|evening|tonight|gabi|hapon|umaga)\b/.test(text)) return {value:'',issue:'Choose an exact time in the preview.',dateOnly:false}
  if (/\b\d{1,2}\b/.test(text.replace(calendarPattern,''))) return {value:'',issue:'Specify AM or PM, or choose the time in the preview.',dateOnly:false}
  if (field==='scheduled_at') return {value:'',issue:'Choose a start time in the preview.',dateOnly:false}
  return {value:day+'T23:59',dateOnly:true}
}

function briefPriority(text: string): TaskBriefForm['priority'] {
  const signals=[...text.matchAll(/\b(?:high\s+priority|low\s+priority|normal\s+priority|priority\s*(?:is|ay|:|=)?\s*(?:high|urgent|normal|low)|urgent|madalian|apurahan)\b/gi)]
  let priority:TaskBriefForm['priority']='normal'
  for(const signal of signals){
    const before=clauseBefore(text,signal.index!).trim()
    const negated=/(?:not|no|hindi|huwag)(?:\s+(?:ito|to|naman|muna))?\s*$/.test(before)
    priority=negated||/\b(?:normal|low)\b/i.test(signal[0])?'normal':/\b(?:urgent|madalian|apurahan)\b/i.test(signal[0])?'urgent':'high'
  }
  return priority
}

function briefTitle(text: string, mentions: BriefMention[], timing: {start:number;end:number}[]): string {
  let content=text
  for(const m of [...mentions].sort((a,b)=>b.start-a.start)) content=content.slice(0,m.start)+(isReference(text,m,mentions)?' '+m.label:' '.repeat(m.end-m.start))+content.slice(m.end)
  for(const range of [...timing].sort((a,b)=>b.start-a.start)) content=content.slice(0,range.start)+' '.repeat(range.end-range.start)+content.slice(range.end)
  const candidates=content.split(/[.!?\n]+/).map(line=>line
    .replace(/\b(?:high\s+priority|normal\s+priority|low\s+priority|priority\s*(?:is|ay|:|=)?\s*(?:high|urgent|normal|low)|urgent|madalian|apurahan)\b/gi,'')
    .replace(/\b(?:with|assigned\s+to|assign\s+to|owner|team|sa|from|kay|kina)\s*[,;]?\s*(?:(?:and|at|kay|kina)\s*)*$/i,'')
    .replace(/\b(?:with|assigned\s+to|assign\s+to)\b\s*[,;:]?\s*(?:and\s*)?$/i,'')
    .replace(/^[\s,:;-]*(?:(?:and|at|with|sa|kay|kina|team|for|to)\b[\s,:;-]*)+/i,'')
    .replace(/[\s,;:-]+$/,'').trim())
  let title=candidates.find(line=>line && !/^(?:and|at|with|from|sa|ng|kay|kina|team|not|no|hindi|huwag|today|ngayon|tomorrow|bukas|makalawa|entire|whole|all|buong|lahat)\s*$/i.test(line))||''
  const taglish=title.match(/^(?:ipagawa|pagawin|paki(?:gawa)?|i-assign)\b.*?\bang\s+(.+)$/i)
  if(taglish) title=taglish[1]!
  title=title.replace(/^(?:please|paki)\s+/i,'')
    .replace(/^(?:gumawa|gawin|i-create)\s+(?:(?:kayo|ka|po|naman|ngayon|now)\s+)*(?:ng\s+)?/i,'Create ')
    .replace(/^(?:i-review|suriin)\s+/i,'Review ')
    .replace(/\s+na\s+highly\s+edited\b/i,' highly edited')
    .replace(/\s+for\s+our\b/i,' for')
    .replace(/\s+(?:today|ngayon|tomorrow|bukas|makalawa)(?:\s+(?:and|at|ang))*\s*$/i,'')
    .replace(/\s+(?:and|at|ang)\s*$/i,'').replace(/\s+/g,' ').trim()
  if (/^\d+\s/.test(title)) title='Create '+title
  return title?title[0]!.toUpperCase()+title.slice(1):''
}

function clauseBefore(text: string, at: number) { return text.slice(0,at).split(/[.;!\n]/).pop()!.toLowerCase() }
function roleContext(text: string, m: BriefMention, mentions: BriefMention[]) {
  let before = text.slice(0,m.start)
  for (const prior of [...mentions].filter(token=>token.end<=m.start).sort((a,b)=>b.start-a.start)) before=before.slice(0,prior.start)+before.slice(prior.end)
  return clauseBefore(before,before.length).replace(/(?:\s|,|&|\band\b|\bat\b)+$/g,' ').trimEnd()
}
function isReference(text: string, m: BriefMention, mentions: BriefMention[]) {
  const before=roleContext(text,m,mentions)
  return /(?:designs?|files?|assets?|work|output|reference|brief|gawa|disenyo)\s+(?:by|from|of|ni|ng)$/.test(before) || /(?:using|gamit(?:in)?|reference|consult|reviewer|approver|cc)(?:\s+the)?$/.test(before)
}
function isExcluded(text: string, m: BriefMention, mentions: BriefMention[]) { return /(?:not|except|exclude|hindi|huwag)(?:\s+assign(?:\s+to)?)?(?:\s+(?:kay|kina|to))?$/.test(roleContext(text,m,mentions)) }

export function interpretBrief(text: string, selected: BriefMention[], reference = new Date()): BriefInterpretation {
  const mentions=validMentions(text,selected), issues: BriefIssue[]=[]
  const teams=mentions.filter(m=>m.kind==='team' && !isExcluded(text,m,mentions)), people=mentions.filter(m=>m.kind==='person' && !isExcluded(text,m,mentions) && !isReference(text,m,mentions))
  const teamIds=[...new Set(teams.map(m=>m.id))]
  if (teamIds.length>1) issues.push({field:'team_id',message:'This draft has more than one team. Choose one team in the preview; separate tasks can be created next.',blocking:true})
  const owners=[...new Set(people.map(m=>m.id))]
  const entireTeam=teams.some(m=>{
    const before=clauseBefore(text,m.start)
    return /\b(entire|whole|all(?:\s+members(?:\s+of)?)?|buong|lahat(?:\s+ng)?)(?:\s+(?:the|team))?\s*$/.test(before) && !/\b(?:not|no|hindi|huwag)(?:\s+(?:sa|to|for))?\s+(?:the\s+)?(?:entire|whole|all|buong|lahat)\b/.test(before)
  })
  const teamExclusions=entireTeam&&mentions.some(m=>m.kind==='person'&&isExcluded(text,m,mentions))
  if(teamExclusions)issues.push({field:'assignee_ids',message:'Whole-team ownership conflicts with excluded people. Select the individual owners in the preview.',blocking:true})
  const recurring=/\b(?:every\s+(?:day|week|month|monday|tuesday|wednesday|thursday|friday|saturday|sunday)|bawat\s+(?:araw|linggo|buwan|lunes|martes|biyernes)|araw-araw)\b/i.test(text)
  if (recurring) issues.push({field:'brief',message:'This creates one new task. Remove the recurring instruction and use Workflow library for recurring work.',blocking:true})
  // Mask linked names before reading dates: a person called Friday is not a deadline.
  let timingText=text
  for(const m of [...mentions].sort((a,b)=>b.start-a.start)) timingText=timingText.slice(0,m.start)+' '.repeat(m.end-m.start)+timingText.slice(m.end)
  const candidates=[...timingText.matchAll(/(?:\b(?:due(?:\s+date)?|deadline|by|deliver(?:\s+by)?|finish(?:\s+by)?|submit(?:\s+by)?|ipasa|pasa|hanggang|tapusin)|\b(?:start(?:\s+date(?:\s*(?:&|and)\s*time)?)?|begin|schedule|simulan|simula|magsimula|umpisahan|umpisa))\b(?:\s+(?:on|sa|ng|at)\b)?\s*[:=-]?\s*/gi)]
  const matches=candidates.filter((match,index)=>{
    if(!/^(by|deliver|finish|submit|ipasa|pasa|tapusin)\b/i.test(match[0]))return true
    const phrase=timingText.slice(match.index!+match[0].length,candidates[index+1]?.index??timingText.length).split(/[.;!\n]/)[0]!
    return dateWords.test(phrase)||/\b(?:\d{1,2}(?::\d{2})?\s*(?:am|pm)|eod|mamaya|later|tonight|next\s+week|noon|midnight)\b/i.test(phrase)
  })
  const timing=matches.map((match,index)=>{
    const start=match.index!, phraseStart=start+match[0].length
    const raw=timingText.slice(phraseStart,matches[index+1]?.index??text.length)
    const boundary=raw.search(/[.;!\n]/), end=phraseStart+(boundary<0?raw.length:boundary)
    const phrase=timingText.slice(phraseStart,end).replace(/^(?:(?:na|muna|ay|is|sa|ng|on|at|ang)\s+)+/i,'').trim()
    const field:'deadline'|'scheduled_at'=/^(start|begin|schedule|simul|magsimula|umpisa)/i.test(match[0])?'scheduled_at':'deadline'
    const negated=/(?:do\s+not|don't|not|huwag|hindi(?:\s+muna)?)\s*$/.test(clauseBefore(timingText,start))
    const now=field==='scheduled_at'&&/^(?:now|ngayon)(?:\s|$)/i.test(phrase)&&!dateWords.test(phrase.replace(/^ngayon\b/i,''))&&!/\d|\b(?:noon|midnight|mamaya|later|eod)\b/i.test(phrase)
    return {start,end,phrase,field,negated,now,calendar:calendarDate(phrase,reference)}
  })
  // Resolve date context before parsing clocks, regardless of clause order.
  const startDays=[...new Set(timing.filter(t=>t.field==='scheduled_at'&&!t.negated&&!t.calendar.issue).flatMap(t=>t.now?[dateString(localReference(reference))]:t.calendar.day?[t.calendar.day]:[]))]
  const sharedStartDay=startDays.length===1?startDays[0]:undefined
  let deadline='',scheduled_at='',dateOnly=false,deadlineInherited=false,start_type:'now'|'scheduled'='now'
  const seen:Partial<Record<'deadline'|'scheduled_at',string>>={}
  for (const entry of timing) {
    const {field,phrase}=entry
    if(entry.negated){issues.push({field,message:'A timing restriction needs an explicit date in the preview.',blocking:true});continue}
    if(entry.now){start_type='now';scheduled_at='';continue}
    const timeOnly=/\b\d{1,2}(?::\d{2})?\s*(?:am|pm)\b|\b\d{1,2}:\d{2}\b|(?:at|alas|@)\s*\d{1,2}\b|^\d{1,2}$|\b(?:noon|midnight|eod|mamaya|later|tonight|gabi|hapon|umaga)\b/i.test(phrase)
    const inherited=field==='deadline'&&timeOnly&&!entry.calendar.day&&!entry.calendar.issue&&Boolean(sharedStartDay)
    const parsed=parseBriefDate(phrase,reference,field,inherited?sharedStartDay:undefined)
    if(field==='scheduled_at'){start_type='scheduled';scheduled_at=parsed.value}else{deadline=parsed.value;dateOnly=parsed.dateOnly;deadlineInherited=inherited&&Boolean(parsed.value)}
    if(parsed.issue)issues.push({field,message:parsed.issue,blocking:true})
    if(seen[field]&&parsed.value&&seen[field]!==parsed.value)issues.push({field,message:'This request gives different values for the same timing field. Choose the intended date and time in the preview.',blocking:true})
    if(parsed.value)seen[field]=parsed.value
  }
  // A plain “tomorrow at 3 PM” is treated as a deadline, with its absolute date visible.
  if (!matches.length && dateWords.test(timingText)) {
    const parsed=parseBriefDate(timingText,reference,'deadline');deadline=parsed.value;dateOnly=parsed.dateOnly
    if (parsed.issue) issues.push({field:'deadline',message:parsed.issue,blocking:true})
  }
  if(scheduled_at&&deadline&&deadline<scheduled_at)issues.push({field:'deadline',message:'The deadline is before the scheduled start. Specify a later deadline or its intended date.',blocking:true})
  const priority=briefPriority(timingText)
  let title=briefTitle(text,mentions,timing)
  if (title.length>255) {title=title.slice(0,255);issues.push({field:'title',message:'The title was shortened. Review it before creating.',blocking:false})}
  const unselected=[...text.matchAll(/(^|[\s([{])@([\p{L}][^\n@,;.!?]{0,70})/gu)].some(m=>!mentions.some(token=>token.start===m.index!+m[1]!.length))
  if(unselected) issues.push({field:'mentions',message:'Select each @mention from the suggestions to link an exact person or team.',blocking:true})
  let description=text.trim()
  for(const m of [...mentions].sort((a,b)=>b.start-a.start)) description=description.slice(0,m.start)+m.label+description.slice(m.end)
  return {fields:{title,description,team_id:teamIds.length===1?teamIds[0]!:'',assignee_ids:owners,priority,start_type,scheduled_at,deadline},ownership:owners.length?'people':entireTeam&&!teamExclusions&&teamIds.length===1?'team':teamIds.length?'unresolved':'unassigned',issues,dateOnly,deadlineInherited}
}
