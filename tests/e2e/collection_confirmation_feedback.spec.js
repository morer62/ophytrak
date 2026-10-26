const {test,expect}=require('@playwright/test');
const fs=require('fs'),path=require('path'),vm=require('vm');
const source=fs.readFileSync(path.resolve(__dirname,'../../src/views/panel/level4/planner-hub/team/driver-mode/index.twig'),'utf8');
const start=source.indexOf("document.getElementById('confirmCollection')?.addEventListener");
const end=source.indexOf('const agentChangeTokens=',start);
function confirmation(payload, networkError=false){
  let handler,resolve;
  const feedback={children:[],replaceChildren(){this.children=[]},appendChild(v){this.children.push(v)}};
  const button={disabled:false,addEventListener(_,fn){handler=fn}};
  const tokens=['BR265885228923I','BR268644790429G'];
  const rows=tokens.map(token=>({dataset:{collectionInput:token},removed:false,remove(){this.removed=true}}));
  const elements={confirmCollection:button,collectionFeedback:feedback,collectionHandedBy:{value:'QA'},collectionCount:{textContent:'2'}};
  const timers=[],calls=[];
  const gate=new Promise(r=>resolve=r);
  const context=vm.createContext({FormData,Set,JSON,collectionTokens:tokens,signatureCanvas:{toDataURL:()=> 'data:image/png;base64,synthetic'},
    window:{location:{href:'https://ophytrack.com/panel/planner-hub/team/driver-mode',reload(){}}},
    document:{getElementById:id=>elements[id]||null,createElement:()=>({textContent:'',className:''}),querySelectorAll:()=>rows},
    fetch:async()=>{calls.push('fetch');await gate;if(networkError)throw new Error('Network unavailable');return {json:async()=>payload}},setTimeout:fn=>timers.push(fn)});
  vm.runInContext(source.slice(start,end),context);
  return {run:()=>handler.call(button),resolve,button,feedback,tokens,rows,timers,calls};
}
test('partial confirmation keeps only failed labels and shows escaped per-label reasons',async()=>{
  const c=confirmation({success:true,message:'1 confirmado · 1 pendiente',packages:[{input:'BR265885228923I'}],errors:['reason'],error_details:[{reference:'BR268644790429G',message:'<img src=x onerror=alert(1)> falta referencia'}]});
  const first=c.run();expect(c.button.disabled).toBe(true);await c.run();expect(c.calls.length).toBe(1);c.resolve();await first;
  expect(c.tokens).toEqual(['BR268644790429G']);expect(c.rows[0].removed).toBe(true);expect(c.rows[1].removed).toBe(false);
  expect(c.feedback.children[1].textContent).toContain('BR268644790429G: <img');expect(c.feedback.children[1].innerHTML).toBeUndefined();
  expect(c.timers).toHaveLength(0);expect(c.button.disabled).toBe(false);
});
test('network error preserves all staged labels and re-enables confirmation',async()=>{
  const c=confirmation(null,true);const work=c.run();c.resolve();await work;
  expect(c.tokens).toHaveLength(2);expect(c.timers).toHaveLength(0);expect(c.button.disabled).toBe(false);
  expect(c.feedback.children[0].textContent).toContain('La lista se conserva');
});
test('all successful labels are removed and the page refreshes only after full success',async()=>{
  const c=confirmation({success:true,message:'2 confirmados',packages:[{input:'BR265885228923I'},{input:'BR268644790429G'}],errors:[]});
  const work=c.run();c.resolve();await work;expect(c.tokens).toHaveLength(0);expect(c.timers).toHaveLength(1);
});
