(function(){
'use strict';
if(typeof window.pdfjsLib!=='undefined' && window.EPF_CONFIG){window.pdfjsLib.GlobalWorkerOptions.workerSrc=EPF_CONFIG.workerUrl;}
const clamp=(n,min,max)=>Math.max(min,Math.min(max,n));
class EPFViewer{
  constructor(root){this.root=root;this.url=this.normalizeUrl(root.dataset.pdf);this.fallbackUrl=this.normalizeUrl(root.dataset.fallbackPdf||'');this.mode=root.dataset.mode||'flipbook';this.page=parseInt(root.dataset.start||'1',10)||1;this.pdf=null;this.zoom=1;this.renderToken=0;this.cache=new Map();this.mobile=window.matchMedia('(max-width:820px)');this.touchStart=null;this.thumbBuilt=false;this.isFirefoxDesktop=/Firefox\//i.test(navigator.userAgent||'')&&!/Android|Mobile|Tablet/i.test(navigator.userAgent||'');this.animating=false;this.reduceMotion=window.matchMedia('(prefers-reduced-motion: reduce)');this.bind();this.load();}
  q(s){return this.root.querySelector(s)} qa(s){return [...this.root.querySelectorAll(s)]}
  normalizeUrl(url){
    if(!url)return '';
    try{
      const u=new URL(url,location.href),here=new URL(location.href);
      const norm=h=>String(h||'').replace(/^www\./i,'').toLowerCase();
      if(norm(u.hostname)===norm(here.hostname)&&u.pathname.indexOf('/wp-content/uploads/')!==-1){u.protocol=here.protocol;u.host=here.host;}
      return u.href;
    }catch(e){return url;}
  }
  async waitForPdfJs(timeout=8000){
    const started=Date.now();
    while(typeof window.pdfjsLib==='undefined' && Date.now()-started<timeout){await new Promise(r=>setTimeout(r,60));}
    return typeof window.pdfjsLib!=='undefined';
  }
  bind(){
    this.qa('.epf-prev,.epf-side-prev,.epf-prev-mobile').forEach(b=>b.addEventListener('click',()=>this.prev()));
    this.qa('.epf-next,.epf-side-next,.epf-next-mobile').forEach(b=>b.addEventListener('click',()=>this.next()));
    const input=this.q('.epf-page-input');input.addEventListener('change',()=>this.go(parseInt(input.value,10)||1));input.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();this.go(parseInt(input.value,10)||1);input.blur();}});
    this.q('.epf-zoom-in').addEventListener('click',()=>this.setZoom(this.zoom+.15));this.q('.epf-zoom-out').addEventListener('click',()=>this.setZoom(this.zoom-.15));
    const fs=this.q('.epf-fullscreen');fs.addEventListener('click',()=>this.fullscreen());
    const tt=this.q('.epf-thumbs-toggle');if(tt)tt.addEventListener('click',()=>this.toggleThumbs());const tc=this.q('.epf-thumbs-close');if(tc)tc.addEventListener('click',()=>this.toggleThumbs(false));
    const sh=this.q('.epf-share');if(sh)sh.addEventListener('click',()=>this.share());
    const stage=this.q('.epf-stage');stage.addEventListener('keydown',e=>{if(e.key==='ArrowRight'||e.key==='PageDown'){e.preventDefault();this.next();}if(e.key==='ArrowLeft'||e.key==='PageUp'){e.preventDefault();this.prev();}if(e.key==='Home'){e.preventDefault();this.go(1);}if(e.key==='End'&&this.pdf){e.preventDefault();this.go(this.pdf.numPages);}});
    stage.addEventListener('touchstart',e=>{if(e.touches.length===1)this.touchStart={x:e.touches[0].clientX,y:e.touches[0].clientY};},{passive:true});stage.addEventListener('touchend',e=>{if(!this.touchStart||!e.changedTouches[0])return;const dx=e.changedTouches[0].clientX-this.touchStart.x,dy=e.changedTouches[0].clientY-this.touchStart.y;this.touchStart=null;if(Math.abs(dx)>55&&Math.abs(dx)>Math.abs(dy)*1.25){dx<0?this.next():this.prev();}},{passive:true});
    this.mobile.addEventListener?this.mobile.addEventListener('change',()=>this.render()):window.addEventListener('resize',()=>this.render());
    window.addEventListener('resize',this.debounce(()=>this.render(),180));
  }
  debounce(fn,ms){let t;return()=>{clearTimeout(t);t=setTimeout(fn,ms)}}
  updateProgress(loaded,total){
    if(!total)return;
    const pct=Math.max(0,Math.min(100,Math.round((loaded/total)*100)));
    const prog=this.q('.epf-loading-progress');
    if(prog)prog.textContent=pct+'%';
  }
  async acceptPdfTask(task,sourceUrl=''){
    task.onProgress=p=>{if(p&&p.total)this.updateProgress(p.loaded,p.total);};
    this.pdf=await task.promise;
    if(sourceUrl)this.url=sourceUrl;
    this.page=clamp(this.page,1,this.pdf.numPages);
    this.q('.epf-total').textContent=this.pdf.numPages;
    this.q('.epf-mobile-total').textContent=this.pdf.numPages;
    await this.render();
    this.q('.epf-loading').classList.add('is-done');
    setTimeout(()=>{const l=this.q('.epf-loading');if(l)l.style.display='none'},280);
    this.prefetch();
  }
  xhrArrayBuffer(url){
    return new Promise((resolve,reject)=>{
      if(!url){reject(new Error('PDF URL is empty.'));return;}
      const xhr=new XMLHttpRequest();
      xhr.open('GET',url,true);
      xhr.responseType='arraybuffer';
      xhr.timeout=120000;
      xhr.setRequestHeader('Accept','application/pdf,*/*;q=0.8');
      xhr.onprogress=e=>{if(e.lengthComputable)this.updateProgress(e.loaded,e.total);};
      xhr.onerror=()=>reject(new Error('Firefox could not download the PDF file.'));
      xhr.ontimeout=()=>reject(new Error('PDF download timed out.'));
      xhr.onabort=()=>reject(new Error('PDF download was cancelled.'));
      xhr.onload=()=>{
        if(xhr.status<200||xhr.status>=300){reject(new Error('PDF HTTP '+xhr.status));return;}
        const buf=xhr.response;
        if(!buf||buf.byteLength<5){reject(new Error('Empty PDF response'));return;}
        const sig=new Uint8Array(buf,0,5);
        const signature=String.fromCharCode(sig[0],sig[1],sig[2],sig[3],sig[4]);
        if(signature!=='%PDF-'){
          const type=(xhr.getResponseHeader('Content-Type')||'').toLowerCase();
          reject(new Error('The server did not return a PDF file'+(type?' ('+type+')':'')));
          return;
        }
        resolve(buf);
      };
      try{xhr.send();}catch(e){reject(e);}
    });
  }
  async loadFirefox(){
    let lastError=null;
    const sources=[];
    // Firefox is stricter about malformed/partial Range responses. Prefer the original
    // Media Library file and fetch it once as a full binary payload without Range requests.
    if(this.fallbackUrl)sources.push({url:this.fallbackUrl,label:'direct'});
    if(this.url&&this.url!==this.fallbackUrl)sources.push({url:this.url,label:'stream'});
    for(const source of sources){
      try{
        const buf=await this.xhrArrayBuffer(source.url);
        const task=pdfjsLib.getDocument({data:new Uint8Array(buf),isEvalSupported:false,disableRange:true,disableStream:true,disableAutoFetch:true});
        await this.acceptPdfTask(task,source.url);
        return true;
      }catch(e){lastError=e;console.warn('ELIMO PDF Flipbook Firefox '+source.label+' source failed:',source.url,e);}
    }
    if(lastError)throw lastError;
    return false;
  }
  async load(){
    const ready=await this.waitForPdfJs();
    if(!ready){this.fail('PDF.js is not available.');return;}
    if(window.EPF_CONFIG&&EPF_CONFIG.workerUrl)window.pdfjsLib.GlobalWorkerOptions.workerSrc=EPF_CONFIG.workerUrl;

    // Firefox desktop path only: avoid PDF.js Range/fetch transport because some LiteSpeed/server
    // combinations return partial responses that Firefox desktop rejects while Chrome and mobile browsers accept them.
    if(this.isFirefoxDesktop){
      try{if(await this.loadFirefox())return;}catch(e){console.warn('ELIMO PDF Flipbook Firefox binary mode failed:',e);}
    }

    const attempts=[];
    if(this.url)attempts.push({url:this.url,label:'stream'});
    if(this.fallbackUrl&&this.fallbackUrl!==this.url)attempts.push({url:this.fallbackUrl,label:'direct'});
    let lastError=null;
    for(const attempt of attempts){
      try{
        const task=pdfjsLib.getDocument({url:attempt.url,withCredentials:false,isEvalSupported:false,rangeChunkSize:262144});
        await this.acceptPdfTask(task,attempt.url);
        return;
      }catch(e){lastError=e;console.warn('ELIMO PDF Flipbook '+attempt.label+' source failed:',attempt.url,e);}
    }

    // Generic full-file binary fallback. XHR is intentionally used instead of fetch because
    // Firefox can report a NetworkError for a response that XMLHttpRequest can read correctly.
    const binarySources=[];
    if(this.fallbackUrl)binarySources.push(this.fallbackUrl);
    if(this.url&&this.url!==this.fallbackUrl)binarySources.push(this.url);
    for(const source of binarySources){
      try{
        const buf=await this.xhrArrayBuffer(source);
        const task=pdfjsLib.getDocument({data:new Uint8Array(buf),isEvalSupported:false,disableRange:true,disableStream:true});
        await this.acceptPdfTask(task,source);
        return;
      }catch(e){lastError=e;console.warn('ELIMO PDF Flipbook binary fallback failed:',source,e);}
    }
    console.error('ELIMO PDF Flipbook:',lastError);
    this.fail(lastError&&lastError.message?lastError.message:'');
  }
  fail(message=''){const l=this.q('.epf-loading');if(l)l.style.display='none';const err=this.q('.epf-error');if(err){err.hidden=false;if(message){const d=err.querySelector('.epf-error-detail');if(d){d.textContent=message;d.hidden=false;}}const direct=err.querySelector('a');if(direct&&this.fallbackUrl)direct.href=this.fallbackUrl;}}
  isSingle(){return this.mode==='single'||this.mobile.matches;}
  normalizePage(p){p=clamp(p,1,this.pdf?this.pdf.numPages:1);if(!this.isSingle()&&p>1&&p%2===1)p-=1;return p;}
  spread(){const p=this.normalizePage(this.page);if(this.isSingle())return [null,p];if(p===1)return [null,1];return [p,p+1<=this.pdf.numPages?p+1:null];}
  async render(){if(!this.pdf)return;const token=++this.renderToken;this.page=this.normalizePage(this.page);const [left,right]=this.spread();this.q('.epf-book').classList.toggle('epf-single',this.isSingle()||!left);const box=this.q('.epf-stage').getBoundingClientRect();const availW=Math.max(200,box.width-(this.mobile.matches?72:130));const availH=Math.max(250,box.height-(this.mobile.matches?48:70));const count=left?2:1;let sample=await this.pdf.getPage(right||left||1);if(token!==this.renderToken)return;const base=sample.getViewport({scale:1});let scale=Math.min((availW/count)/base.width,availH/base.height)*this.zoom;scale=clamp(scale,.22,3.5);await Promise.all([this.renderSlot('.epf-left-page',left,scale,token),this.renderSlot('.epf-right-page',right,scale,token)]);if(token!==this.renderToken)return;this.updateUI();this.highlightThumb();}
  async renderSlot(sel,pageNo,scale,token){const slot=this.q(sel),canvas=slot.querySelector('canvas'),num=slot.querySelector('.epf-page-number');if(!pageNo){slot.style.display='none';canvas.width=1;canvas.height=1;num.textContent='';return;}slot.style.display='flex';const page=await this.pdf.getPage(pageNo);if(token!==this.renderToken)return;const dpr=Math.min(window.devicePixelRatio||1,2);const viewport=page.getViewport({scale:scale*dpr});const cssW=viewport.width/dpr,cssH=viewport.height/dpr;canvas.width=Math.ceil(viewport.width);canvas.height=Math.ceil(viewport.height);canvas.style.width=cssW+'px';canvas.style.height=cssH+'px';slot.style.width=cssW+'px';slot.style.height=cssH+'px';num.textContent=pageNo;const ctx=canvas.getContext('2d',{alpha:false});ctx.save();ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height);ctx.restore();await page.render({canvasContext:ctx,viewport}).promise;}
  updateUI(){const visible=this.spread().filter(Boolean);const display=visible.length>1?visible[0]+'–'+visible[visible.length-1]:visible[0];this.q('.epf-page-input').value=visible[0]||1;this.q('.epf-mobile-current').textContent=display;const prevDisabled=this.page<=1,nextDisabled=visible[visible.length-1]>=this.pdf.numPages;this.qa('.epf-prev,.epf-side-prev,.epf-prev-mobile').forEach(b=>b.disabled=prevDisabled);this.qa('.epf-next,.epf-side-next,.epf-next-mobile').forEach(b=>b.disabled=nextDisabled);}
  async renderTurnFace(canvas,pageNo,cssW,cssH){
    const dpr=Math.min(window.devicePixelRatio||1,2);
    canvas.width=Math.max(1,Math.ceil(cssW*dpr));canvas.height=Math.max(1,Math.ceil(cssH*dpr));
    canvas.style.width=cssW+'px';canvas.style.height=cssH+'px';
    const ctx=canvas.getContext('2d',{alpha:false});ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height);
    if(!pageNo)return;
    const page=await this.pdf.getPage(pageNo),base=page.getViewport({scale:1});
    const fit=Math.min(cssW/base.width,cssH/base.height);const vp=page.getViewport({scale:fit*dpr});
    const ox=Math.max(0,(canvas.width-vp.width)/2),oy=Math.max(0,(canvas.height-vp.height)/2);
    await page.render({canvasContext:ctx,viewport:vp,transform:[1,0,0,1,ox,oy]}).promise;
  }
  copyCanvas(source,target){
    const cssW=parseFloat(source.style.width)||source.getBoundingClientRect().width||1,cssH=parseFloat(source.style.height)||source.getBoundingClientRect().height||1;
    target.width=source.width;target.height=source.height;target.style.width=cssW+'px';target.style.height=cssH+'px';
    const ctx=target.getContext('2d',{alpha:false});ctx.fillStyle='#fff';ctx.fillRect(0,0,target.width,target.height);ctx.drawImage(source,0,0);
  }
  async naturalTurn(targetPage,dir){
    if(!this.pdf||this.animating)return;
    if(this.isSingle()||this.reduceMotion.matches){this.page=targetPage;await this.render();this.prefetch();return;}
    const current=this.spread(),oldPage=dir>0?current[1]:current[0];
    const oldSlot=this.q(dir>0?'.epf-right-page':'.epf-left-page');
    if(!oldPage||!oldSlot||oldSlot.style.display==='none'){this.page=targetPage;await this.render();this.prefetch();return;}
    this.animating=true;
    const stage=this.q('.epf-stage'),srcCanvas=oldSlot.querySelector('canvas'),stageRect=stage.getBoundingClientRect(),srcRect=oldSlot.getBoundingClientRect();
    const targetOldPage=this.page;this.page=targetPage;const targetSpread=this.spread();this.page=targetOldPage;
    const backPage=dir>0?targetSpread[0]:targetSpread[1];
    const sheet=document.createElement('div');sheet.className='epf-flip-sheet '+(dir>0?'epf-flip-next':'epf-flip-prev');
    sheet.style.width=srcRect.width+'px';sheet.style.height=srcRect.height+'px';sheet.style.left=(srcRect.left-stageRect.left)+'px';sheet.style.top=(srcRect.top-stageRect.top)+'px';
    sheet.innerHTML='<div class="epf-flip-face epf-flip-front"><canvas></canvas><span class="epf-flip-shade"></span></div><div class="epf-flip-face epf-flip-back"><canvas></canvas><span class="epf-flip-shade"></span></div><span class="epf-flip-edge"></span>';
    stage.appendChild(sheet);
    this.copyCanvas(srcCanvas,sheet.querySelector('.epf-flip-front canvas'));
    await this.renderTurnFace(sheet.querySelector('.epf-flip-back canvas'),backPage,srcRect.width,srcRect.height);
    const book=this.q('.epf-book');book.classList.add('epf-book-turning');
    requestAnimationFrame(()=>requestAnimationFrame(()=>sheet.classList.add('is-turning')));
    const swapAt=430,finishAt=760;
    await new Promise(r=>setTimeout(r,swapAt));
    this.page=targetPage;await this.render();
    const remain=Math.max(0,finishAt-swapAt);await new Promise(r=>setTimeout(r,remain));
    sheet.remove();book.classList.remove('epf-book-turning');this.animating=false;this.prefetch();
  }
  next(){if(!this.pdf||this.animating)return;const step=this.isSingle()?1:(this.page===1?1:2);const next=clamp(this.page+step,1,this.pdf.numPages);if(next===this.page)return;this.naturalTurn(next,1).catch(()=>{this.animating=false;this.page=next;this.render();this.prefetch()})}
  prev(){if(!this.pdf||this.animating)return;let prev=this.isSingle()?this.page-1:(this.page<=2?1:this.page-2);prev=clamp(prev,1,this.pdf.numPages);if(prev===this.page)return;this.naturalTurn(prev,-1).catch(()=>{this.animating=false;this.page=prev;this.render();this.prefetch()})}
  go(p){if(!this.pdf)return;this.page=this.normalizePage(p);this.render();this.prefetch()}
  setZoom(z){this.zoom=clamp(z,.65,2.5);this.render();this.toast(Math.round(this.zoom*100)+'%')}
  async toggleThumbs(force){const panel=this.q('.epf-thumbs');if(!panel)return;const open=force!==undefined?force:panel.hidden;panel.hidden=!open;if(open&&!this.thumbBuilt){this.thumbBuilt=true;await this.buildThumbs();}}
  async buildThumbs(){const list=this.q('.epf-thumbs-list');for(let n=1;n<=this.pdf.numPages;n++){const b=document.createElement('button');b.type='button';b.className='epf-thumb';b.dataset.page=n;b.innerHTML='<canvas></canvas><span>'+n+'</span>';b.addEventListener('click',()=>{this.go(n);if(this.mobile.matches)this.toggleThumbs(false)});list.appendChild(b);this.renderThumb(b,n);}}
  async renderThumb(btn,n){try{const page=await this.pdf.getPage(n);const vp=page.getViewport({scale:.2});const c=btn.querySelector('canvas');c.width=Math.ceil(vp.width);c.height=Math.ceil(vp.height);await page.render({canvasContext:c.getContext('2d'),viewport:vp}).promise;}catch(e){}}
  highlightThumb(){this.qa('.epf-thumb').forEach(b=>b.classList.toggle('is-active',this.spread().includes(parseInt(b.dataset.page,10))))}
  prefetch(){if(!this.pdf)return;const cur=this.page;[cur-2,cur-1,cur+1,cur+2,cur+3].filter(n=>n>=1&&n<=this.pdf.numPages).forEach(n=>this.pdf.getPage(n).catch(()=>{}));}
  fullscreen(){if(document.fullscreenElement){document.exitFullscreen&&document.exitFullscreen();return;}this.root.requestFullscreen?this.root.requestFullscreen():this.toast('Fullscreen is not available');setTimeout(()=>this.render(),180)}
  async share(){const data={title:this.q('.epf-title').textContent,url:location.href};try{if(navigator.share){await navigator.share(data);}else if(navigator.clipboard){await navigator.clipboard.writeText(location.href);this.toast('Link copied');}else{this.toast(location.href);}}catch(e){}}
  toast(text){const el=this.q('.epf-toast');el.textContent=text;el.classList.add('is-visible');clearTimeout(this.toastTimer);this.toastTimer=setTimeout(()=>el.classList.remove('is-visible'),1500)}
}
function init(root){if(root.dataset.epfInit)return;root.dataset.epfInit='1';new EPFViewer(root)}
function initAll(scope=document){scope.querySelectorAll('.epf-viewer').forEach(init)}
initAll();
document.addEventListener('click',e=>{const trigger=e.target.closest('[data-epf-open]');if(trigger){const id=trigger.dataset.epfOpen,box=document.getElementById(id+'-lightbox');if(box){box.hidden=false;document.body.style.overflow='hidden';const v=box.querySelector('.epf-viewer');init(v);setTimeout(()=>window.dispatchEvent(new Event('resize')),60)}}const close=e.target.closest('.epf-lightbox-close');if(close){const box=close.closest('.epf-lightbox');box.hidden=true;document.body.style.overflow='';}});
})();
