import test from 'node:test'
import assert from 'node:assert/strict'
import { activeMention, interpretBrief, mergeBriefFields, parseBriefDate, reconcileMentions, retainBriefSafeguards } from '../src/utils/taskBrief.ts'
import type { BriefMention } from '../src/types/taskBrief.ts'

const reference = new Date('2026-09-30T11:00:00Z')
const roster = [{kind:'person',id:11,label:'Carlo'}, {kind:'person',id:12,label:'Mark'}, {kind:'team',id:21,label:'AI Video Editors'}, {kind:'team',id:22,label:'Design'}] as const
function tokens(text: string): BriefMention[] {
  return roster.flatMap(person => {
    const occurrences = [...text.matchAll(new RegExp('@'+person.label, 'g'))]
    return occurrences.map(match => ({...person,start:match.index!,end:match.index!+match[0].length}))
  })
}
const draft = (text: string) => interpretBrief(text,tokens(text),reference)

test('English request separates exact owners, team and deadline', () => {
  const result = draft('Create 3 product videos with @Carlo and @Mark from @AI Video Editors, due Friday at 5 PM. Use the brand guide.')
  assert.equal(result.fields.title,'Create 3 product videos')
  assert.deepEqual(result.fields.assignee_ids,[11,12])
  assert.equal(result.fields.team_id,21)
  assert.equal(result.fields.deadline,'2026-10-02T17:00')
  assert.equal(result.ownership,'people')
  assert.deepEqual(result.issues,[])
})
test('Taglish request understands deadline, priority and assignment', () => {
  const result = draft('Ipagawa kay @Carlo ang 3 product videos, due bukas alas 5 PM. Urgent.')
  assert.equal(result.fields.title,'Create 3 product videos')
  assert.deepEqual(result.fields.assignee_ids,[11])
  assert.equal(result.fields.deadline,'2026-10-01T17:00')
  assert.equal(result.fields.priority,'urgent')
})
test('references do not become owners or false deadlines', () => {
  const result = draft('Create landing page with @Carlo. Use designs by @Mark.')
  assert.deepEqual(result.fields.assignee_ids,[11])
  assert.equal(result.fields.deadline,'')
  assert.deepEqual(result.issues,[])
  assert.deepEqual(draft('Use designs ni @Carlo at @Mark.').fields.assignee_ids,[])
  assert.equal(draft('Create campaign videos. May use CapCut.').fields.deadline,'')
})
test('negated people and lists stay excluded', () => {
  assert.deepEqual(draft('Create product videos, hindi kay @Carlo, with @Mark.').fields.assignee_ids,[12])
  assert.deepEqual(draft('Create product videos. Huwag kina @Carlo at @Mark.').fields.assignee_ids,[])
  assert.equal(draft('Create product videos. Hindi urgent.').fields.priority,'normal')
})
test('a team alone requires explicit whole-team confirmation', () => {
  assert.equal(draft('Create videos with @AI Video Editors.').ownership,'unresolved')
  assert.equal(draft('Create videos with the whole @AI Video Editors.').ownership,'team')
  assert.equal(draft('Gumawa ng videos sa buong @AI Video Editors.').ownership,'team')
})
test('plain names never resolve to IDs and unfinished @mentions require selection', () => {
  assert.deepEqual(draft('Create videos with Carlo.').fields.assignee_ids,[])
  assert.ok(interpretBrief('Create videos with @Carlo',[],reference).issues.some(issue=>issue.field==='mentions'&&issue.blocking))
})
test('a linked weekday name is not interpreted as a date', () => {
  const text='Create video with @Friday'
  const result=interpretBrief(text,[{kind:'person',id:4,label:'Friday',start:18,end:25}],reference)
  assert.equal(result.fields.deadline,'')
})
test('multiple teams and recurrence require review', () => {
  assert.ok(draft('Create video with @Design and @AI Video Editors.').issues.some(issue=>issue.field==='team_id'&&issue.blocking))
  assert.ok(draft('Create a video every Friday.').issues.some(issue=>issue.field==='brief'&&issue.blocking))
})
test('start and deadline remain distinct', () => {
  const result=draft('Create videos with @Carlo. Start bukas at 9 AM, due Friday at 5 PM.')
  assert.equal(result.fields.start_type,'scheduled')
  assert.equal(result.fields.scheduled_at,'2026-10-01T09:00')
  assert.equal(result.fields.deadline,'2026-10-02T17:00')
  assert.deepEqual(result.issues,[])
})
test('date-only deadlines use visible end of calendar day; scheduled starts require a time', () => {
  assert.deepEqual(parseBriefDate('bukas',reference,'deadline'),{value:'2026-10-01T23:59',dateOnly:true})
  assert.ok(parseBriefDate('bukas',reference,'scheduled_at').issue)
})
test('UTC+8 day and year boundaries are used regardless of browser timezone', () => {
  assert.equal(parseBriefDate('today at 9 AM',new Date('2026-09-30T17:00:00Z'),'deadline').value,'2026-10-01T09:00')
  assert.equal(parseBriefDate('tomorrow at 12 AM',new Date('2026-12-31T11:00:00Z'),'deadline').value,'2027-01-01T00:00')
  assert.equal(parseBriefDate('makalawa noon',reference,'deadline').value,'2026-10-02T12:00')
})
test('ambiguous and invalid times are not guessed', () => {
  for(const phrase of ['tomorrow at 5','bukas mamaya','today EOD','2026-02-30 at 5 PM','tomorrow at 13 PM','tomorrow at 5:80 PM'])assert.ok(parseBriefDate(phrase,reference,'deadline').issue,phrase)
})
test('explicit 24-hour times and month dates are supported', () => {
  assert.equal(parseBriefDate('October 3 at 17:30',reference,'deadline').value,'2026-10-03T17:30')
  assert.equal(parseBriefDate('2026-10-03 at 12 PM',reference,'deadline').value,'2026-10-03T12:00')
})
test('mention search excludes email addresses and punctuation', () => {
  assert.equal(activeMention('@Carlo',0),null)
  assert.equal(activeMention('Email support@example.com',25),null)
  assert.deepEqual(activeMention('Assign @Car',11),{start:7,end:11,query:'Car'})
  assert.equal(activeMention('Assign @Carlo, ',14),null)
})
test('mentions shift with edits and retain UTF-16 offsets after emoji', () => {
  const before='Create with @Carlo', after='🎬 '+before, mention=tokens(before)
  const moved=reconcileMentions(before,after,mention)
  assert.equal(moved[0]?.start,15)
  assert.deepEqual(interpretBrief(after,moved,reference).fields.assignee_ids,[11])
})
test('editing or removing a selected mention retracts its binding', () => {
  const before='Create with @Carlo', selected=tokens(before)
  assert.deepEqual(reconcileMentions(before,'Create with @Carl',selected),[])
  const result=interpretBrief('Create with ',reconcileMentions(before,'Create with ',selected),reference)
  assert.deepEqual(result.fields.assignee_ids,[])
})
test('manually corrected values remain fixed while untouched fields update', () => {
  const current=draft('Create video with @Carlo').fields, incoming=draft('Create banner with @Mark. Urgent.').fields
  current.title='Approved exact title'
  const merged=mergeBriefFields(current,incoming,{title:true,assignee_ids:true})
  assert.equal(merged.title,'Approved exact title')
  assert.deepEqual(merged.assignee_ids,[11])
  assert.equal(merged.priority,'urgent')
  assert.notEqual(merged.assignee_ids,current.assignee_ids)
  assert.equal(current.priority,'normal')
})
test('AI cannot turn a contextual team mention into a broadcast', () => {
  const local=draft('Create videos with @AI Video Editors.'), proposed={...local,ownership:'team' as const,issues:[]}
  assert.equal(retainBriefSafeguards(local,proposed).ownership,'unresolved')
  const explicit=draft('Create videos with the entire team @AI Video Editors.')
  assert.equal(retainBriefSafeguards(explicit,{...explicit,issues:[]}).ownership,'team')
  assert.equal(draft('Create videos, not the whole @AI Video Editors.').ownership,'unresolved')
})
test('AI cannot erase known unsupported recurrence or multiple-team warnings', () => {
  const local=draft('Create videos every Friday with @Design and @AI Video Editors.')
  const guarded=retainBriefSafeguards(local,{...local,issues:[]})
  assert.ok(guarded.issues.some(issue=>issue.field==='brief'&&issue.blocking))
  assert.ok(guarded.issues.some(issue=>issue.field==='team_id'&&issue.blocking))
})
test('whole-team ownership with an excluded person requires individual selection', () => {
  const local=draft('Create videos with the whole @AI Video Editors, except @Mark.')
  assert.equal(local.ownership,'unresolved')
  assert.ok(retainBriefSafeguards(local,{...local,ownership:'team',issues:[]}).issues.some(issue=>issue.field==='assignee_ids'&&issue.blocking))
})

test('multiline mention headers generate a title and inherit the start date for a time-only deadline', () => {
  const text='@AI Video Editors\n@Carlo Mendoza and @Mark Dela Cruz @Anna Santos\ngumawa kayo ngayon ng ai video na highly edited for our FB Meta ADS bukas. start na bukas ng 10:32pm at ang deadline 11pm'
  const mentions:BriefMention[]=[{kind:'team',id:21,label:'AI Video Editors'},{kind:'person',id:11,label:'Carlo Mendoza'},{kind:'person',id:12,label:'Mark Dela Cruz'},{kind:'person',id:13,label:'Anna Santos'}].map(m=>({...m,start:text.indexOf('@'+m.label),end:text.indexOf('@'+m.label)+m.label.length+1}))
  const result=interpretBrief(text,mentions,new Date('2026-10-01T11:00:00Z'))
  assert.equal(result.fields.title,'Create ai video highly edited for FB Meta ADS')
  assert.equal(result.fields.priority,'normal')
  assert.equal(result.fields.scheduled_at,'2026-10-02T22:32')
  assert.equal(result.fields.deadline,'2026-10-02T23:00')
  assert.equal(result.deadlineInherited,true)
  assert.deepEqual(result.issues,[])
})

test('deadline date inheritance is independent of clause order and explicit dates take precedence', () => {
  const result=draft('Create videos. Deadline 5 PM; start bukas at 9 AM.')
  assert.equal(result.fields.deadline,'2026-10-01T17:00')
  assert.equal(result.deadlineInherited,true)
  const explicit=draft('Create videos. Start bukas at 9 AM; deadline Friday at 5 PM.')
  assert.equal(explicit.fields.deadline,'2026-10-02T17:00')
  assert.equal(explicit.deadlineInherited,false)
})

test('an earlier time-only deadline stays on the start day and requires correction', () => {
  const result=draft('Create videos. Start bukas at 10:32 PM; deadline 10 PM.')
  assert.equal(result.fields.deadline,'2026-10-01T22:00')
  assert.ok(result.issues.some(issue=>issue.field==='deadline'&&issue.blocking))
  const overnight=draft('Create videos. Start bukas at 10:32 PM; deadline makalawa at 1 AM.')
  assert.equal(overnight.fields.deadline,'2026-10-02T01:00')
  assert.deepEqual(overnight.issues,[])
})

test('missing or conflicting timing context never supplies an invented deadline day', () => {
  assert.equal(draft('Create videos. Deadline 11 PM.').fields.deadline,'')
  const conflicting=draft('Create videos. Start bukas or Friday at 9 AM; deadline 11 PM.')
  assert.equal(conflicting.fields.scheduled_at,'')
  assert.equal(conflicting.fields.deadline,'')
  assert.ok(conflicting.issues.some(issue=>issue.field==='scheduled_at'&&issue.blocking))
  assert.ok(draft('Create videos. Deadline Friday at 5 PM or 6 PM.').issues.some(issue=>issue.field==='deadline'&&issue.blocking))
})

test('explicit start now supplies today for a time-only deadline', () => {
  const result=draft('Create videos. Start na ngayon; deadline 11 PM.')
  assert.equal(result.fields.start_type,'now')
  assert.equal(result.fields.scheduled_at,'')
  assert.equal(result.fields.deadline,'2026-09-30T23:00')
  const timed=draft('Create videos. Start ngayon at 9 PM; deadline 11 PM.')
  assert.equal(timed.fields.start_type,'scheduled')
  assert.equal(timed.fields.scheduled_at,'2026-09-30T21:00')
})

test('priority directives respect word order, Taglish, corrections and negation without matching quality', () => {
  assert.equal(draft('Create high quality, highly edited video.').fields.priority,'normal')
  assert.equal(draft('Create videos. Priority: high.').fields.priority,'high')
  assert.equal(draft('Create videos. Madalian ito.').fields.priority,'urgent')
  assert.equal(draft('Create videos. Urgent, actually priority normal.').fields.priority,'normal')
  assert.equal(draft('Create videos. Hindi urgent, high priority lang.').fields.priority,'high')
  assert.equal(draft('Create videos. Hindi naman urgent.').fields.priority,'normal')
  assert.equal(draft('Create videos. High priority, then urgent.').fields.priority,'urgent')
})

test('titles skip mention-only headers and retain reference subjects', () => {
  assert.equal(draft('@Carlo\n@AI Video Editors\nCreate 3 campaign videos. Due Friday at 5 PM.').fields.title,'Create 3 campaign videos')
  assert.equal(draft('@Carlo\n@AI Video Editors').fields.title,'')
  assert.equal(draft('Review designs by @Mark.').fields.title,'Review designs by Mark')
})

test('unsupported or incomplete deadlines never borrow a start date as if explicitly requested', () => {
  for(const phrase of ['deadline','deadline next week at 5 PM','deadline 10/02 at 5 PM']){
    const result=draft('Create videos. Start bukas at 9 AM; '+phrase)
    assert.equal(result.fields.deadline,'',phrase)
    assert.ok(result.issues.some(issue=>issue.field==='deadline'&&issue.blocking),phrase)
  }
})
