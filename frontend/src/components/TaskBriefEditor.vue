<template>
  <div class="brief-editor">
    <label :for="editorId">What needs to get done?</label>
    <p :id="editorId + '-hint'" class="field-hint">Write in English or Taglish. Type <strong>@</strong> to select an exact person or team.</p>
    <div class="brief-input" :class="{ 'is-composing': composing }">
      <div ref="highlight" class="brief-highlight" aria-hidden="true" :style="{ width: mirrorWidth ? mirrorWidth + 'px' : undefined }"><span v-for="(segment,index) in segments" :key="index" :class="{ 'brief-inline-mention': segment.linked }">{{ segment.text }}</span>{{ text.endsWith('\n') ? '\n' : '' }}</div>
      <textarea :id="editorId" ref="editor" :value="text" autofocus rows="8" maxlength="6000" :disabled="disabled" :aria-describedby="editorId + '-hint ' + editorId + '-status'" :aria-controls="query ? editorId + '-suggestions' : undefined" :aria-activedescendant="query && suggestions.length ? editorId + '-option-' + activeIndex : undefined" aria-autocomplete="list" :aria-haspopup="query ? 'listbox' : undefined" placeholder="Create 3 product videos with @Carlo and @Mark from @AI Video Editors, due Friday at 5 PM. Use the brand guide." @input="input" @scroll="syncHighlight" @click="updateCaret" @keyup="updateCaret" @keydown="keydown" @compositionstart="composing = true" @compositionend="compositionEnd" />
    </div>
    <div v-if="query" class="brief-mention-menu">
      <div class="brief-mention-caption"><span>{{ query.query ? 'Matches for @' + query.query : 'People & teams' }}</span><span class="small muted">↑ ↓ · Enter · Esc</span></div>
      <ul :id="editorId + '-suggestions'" role="listbox" aria-label="Mention suggestions">
        <li v-for="(suggestion,index) in suggestions" :id="editorId + '-option-' + index" :key="suggestion.kind + '-' + suggestion.id" role="option" :aria-selected="index === activeIndex" :class="{ 'is-selected': index === activeIndex }" @mousedown.prevent @click="select(suggestion)">
          <button type="button" tabindex="-1" @mousedown.prevent><component :is="suggestion.kind === 'team' ? Users : UserRound" aria-hidden="true" /><span class="grow"><strong>{{ suggestion.name }}</strong><span class="small muted">{{ suggestion.caption }}</span></span><ArrowUpRight aria-hidden="true" /></button>
        </li>
      </ul>
      <p v-if="!suggestions.length" class="small muted brief-no-matches">No matching person or team in your workspace.</p>
    </div>
    <div class="brief-editor-bottom"><span :id="editorId + '-status'" class="small muted" role="status" aria-live="polite">{{ query ? suggestions.length + ' suggestions. Use arrow keys and Enter to select.' : 'Your paragraph stays as written.' }}</span><span class="small muted">{{ text.length.toLocaleString() }} / 6,000</span></div>
    <div v-if="linked.length" class="brief-linked" aria-label="Selected mentions"><span v-for="m in linked" :key="m.kind + '-' + m.id" class="badge badge-info"><component :is="m.kind === 'team' ? Users : UserRound" aria-hidden="true" />{{ m.label }}</span></div>
  </div>
</template>
<script setup lang="ts">
import { ref, computed, watch, nextTick, useId, onMounted, onBeforeUnmount } from 'vue'
import { Users, UserRound, ArrowUpRight } from 'lucide-vue-next'
import { activeMention, reconcileMentions, validMentions } from '@/utils/taskBrief'
import type { BriefContext, BriefMention } from '@/types/taskBrief'
const props=defineProps<{text:string;mentions:BriefMention[];context:BriefContext|null;teamId:number|'';disabled?:boolean}>()
const emit=defineEmits<{change:[value:{text:string;mentions:BriefMention[]}];submit:[]}>()
const editor=ref<HTMLTextAreaElement|null>(null), editorId='brief-editor-'+useId(), caret=ref(0), activeIndex=ref(0), dismissed=ref(false), composing=ref(false)
const highlight=ref<HTMLElement|null>(null), mirrorWidth=ref(0)
let resizeObserver:ResizeObserver|undefined
interface Suggestion {kind:'person'|'team';id:number;name:string;caption:string;rank:number}
const query=computed(()=> {
  if(dismissed.value||composing.value||props.disabled) return null
  if(validMentions(props.text,props.mentions).some(m=>caret.value>m.start&&caret.value<=m.end)) return null
  return activeMention(props.text,caret.value)
})
const normalize=(value:string)=>value.normalize('NFD').replace(/\p{M}/gu,'').toLowerCase()
const suggestions=computed<Suggestion[]>(()=> {
  if(!props.context||!query.value) return []
  const q=normalize(query.value.query.trim())
  const people:Suggestion[]=props.context.people.map(person=>({kind:'person',id:person.id,name:person.name,caption:'Person · '+(person.teams.map(t=>t.name).join(', ')||'General'),rank:person.teams.some(t=>t.id===props.teamId)?0:1}))
  const teams:Suggestion[]=props.context.teams.map(team=>({kind:'team',id:team.id,name:team.name,caption:'Team · '+team.member_ids.length+' members',rank:2}))
  return [...people,...teams].filter(item=>normalize(item.name).includes(q)).sort((a,b)=>a.rank-b.rank||a.name.localeCompare(b.name)).slice(0,10)
})
const linked=computed(()=>validMentions(props.text,props.mentions).filter((m,index,all)=>all.findIndex(other=>other.id===m.id&&other.kind===m.kind)===index))
const segments=computed(()=>{
  const result:{text:string;linked:boolean}[]=[];let position=0
  for(const m of validMentions(props.text,props.mentions).sort((a,b)=>a.start-b.start)){
    if(m.start<position)continue
    if(m.start>position)result.push({text:props.text.slice(position,m.start),linked:false})
    result.push({text:props.text.slice(m.start,m.end),linked:true});position=m.end
  }
  if(position<props.text.length)result.push({text:props.text.slice(position),linked:false})
  return result
})
watch(()=>query.value?.query,()=>activeIndex.value=0)
watch(()=>props.text,()=>void nextTick(syncHighlight))
function syncHighlight(){if(!editor.value||!highlight.value)return;mirrorWidth.value=editor.value.clientWidth;highlight.value.scrollTop=editor.value.scrollTop;highlight.value.scrollLeft=editor.value.scrollLeft}
onMounted(()=>{if(editor.value){resizeObserver=new ResizeObserver(syncHighlight);resizeObserver.observe(editor.value)}syncHighlight()})
onBeforeUnmount(()=>resizeObserver?.disconnect())
function updateCaret(){caret.value=editor.value?.selectionStart||0}
function input(event:Event){if(composing.value)return;const element=event.target as HTMLTextAreaElement;dismissed.value=false;caret.value=element.selectionStart;emit('change',{text:element.value,mentions:reconcileMentions(props.text,element.value,props.mentions)})}
function compositionEnd(event:CompositionEvent){composing.value=false;input(event)}
async function select(item:Suggestion){
  const range=query.value;if(!range||!editor.value)return
  const token='@'+item.name, next=props.text.slice(0,range.start)+token+' '+props.text.slice(range.end)
  const mentions=reconcileMentions(props.text,next,props.mentions)
  mentions.push({kind:item.kind,id:item.id,label:item.name,start:range.start,end:range.start+token.length})
  dismissed.value=true;emit('change',{text:next,mentions});await nextTick();editor.value.focus({preventScroll:true});editor.value.setSelectionRange(range.start+token.length+1,range.start+token.length+1);updateCaret()
}
async function keydown(event:KeyboardEvent){
  if(event.isComposing||composing.value)return
  if((event.ctrlKey||event.metaKey)&&event.key==='Enter'){event.preventDefault();emit('submit');return}
  if(!query.value)return
  if(event.key==='Escape'){event.preventDefault();event.stopPropagation();dismissed.value=true;return}
  if((event.key==='ArrowDown'||event.key==='ArrowUp')&&suggestions.value.length){event.preventDefault();activeIndex.value=(activeIndex.value+(event.key==='ArrowDown'?1:-1)+suggestions.value.length)%suggestions.value.length;await nextTick();document.getElementById(editorId+'-option-'+activeIndex.value)?.scrollIntoView({block:'nearest'})}
  else if(event.key==='Enter'&&!event.shiftKey&&suggestions.value[activeIndex.value]){event.preventDefault();void select(suggestions.value[activeIndex.value]!)}
  else if(event.key==='Tab'){dismissed.value=true}
}
defineExpose({focus:()=>editor.value?.focus({preventScroll:true})})
</script>
<style scoped>
.brief-editor{display:flex;flex-direction:column;gap:10px;min-width:0}
.brief-editor>label{font-size:14px;font-weight:650}.brief-editor>.field-hint{margin:0}
.brief-input{position:relative;background:var(--surface);border-radius:10px;isolation:isolate}
.brief-input textarea,.brief-highlight{font:inherit;font-size:14px;line-height:1.85;letter-spacing:normal;padding:18px;white-space:pre-wrap;overflow-wrap:break-word;word-break:normal;tab-size:8}
.brief-input textarea{display:block;position:relative;min-height:260px;resize:vertical;width:100%;margin:0;border:1px solid var(--control-line);border-radius:10px;background:transparent;color:transparent;caret-color:var(--ink);-webkit-text-fill-color:transparent}
.brief-input textarea::placeholder{color:var(--muted);-webkit-text-fill-color:var(--muted)}
.brief-highlight{position:absolute;top:1px;left:1px;width:calc(100% - 2px);height:calc(100% - 2px);overflow:hidden;pointer-events:none;color:var(--ink);border:0}
.brief-inline-mention{color:var(--info);background:var(--info-soft);border-radius:5px;box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--info) 30%,transparent);box-decoration-break:clone;-webkit-box-decoration-break:clone;padding-block:3px}
.brief-input textarea::selection{background:var(--brand-soft);-webkit-text-fill-color:var(--ink);color:var(--ink)}
.brief-input.is-composing textarea{color:var(--ink);-webkit-text-fill-color:var(--ink)}.brief-input.is-composing .brief-highlight{visibility:hidden}
.brief-editor-bottom{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}
.brief-linked{display:flex;gap:6px;flex-wrap:wrap}.brief-linked .badge{white-space:normal;overflow-wrap:anywhere}
.brief-linked svg{width:14px;height:14px}
.brief-mention-menu{background:var(--surface);border:1px solid var(--control-line);border-radius:12px;box-shadow:var(--shadow-raised);overflow:hidden}
.brief-mention-caption{padding:10px 12px;background:var(--surface-soft);display:flex;justify-content:space-between;flex-wrap:wrap;gap:6px;font-size:12px;font-weight:600}
.brief-mention-menu ul{list-style:none;padding:4px;margin:0;max-height:250px;overflow-y:auto;scroll-padding:4px}
.brief-mention-menu li{border-radius:8px}.brief-mention-menu li.is-selected{background:var(--brand-soft)}
.brief-mention-menu button{display:flex;align-items:center;gap:10px;border:0;border-radius:8px;background:transparent;color:var(--ink);padding:10px 12px;width:100%;min-height:52px;text-align:left}
.brief-mention-menu button strong,.brief-mention-menu button .small{display:block;overflow-wrap:anywhere}.brief-mention-menu button strong{font-size:13px}
.brief-no-matches{padding:14px}.brief-mention-menu button>svg:first-child{color:var(--brand)}
@media(max-width:767px){.brief-input textarea{min-height:180px}.brief-input textarea,.brief-highlight{padding:14px;font-size:16px}}
@media(forced-colors:active){.brief-highlight{display:none}.brief-input textarea{color:CanvasText;-webkit-text-fill-color:CanvasText}}
</style>
