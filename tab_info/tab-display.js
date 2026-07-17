(() => {
"use strict";
const form=document.getElementById("displayConfig");
const displayGrid=document.getElementById("displayGrid");
const configPanel=document.getElementById("configPanel");
const configBackdrop=document.getElementById("configBackdrop");
const storageKey="tab-sa-racing-display-v5";

function openConfig(){configPanel.classList.add("is-open");configPanel.classList.remove("is-hidden");configBackdrop.hidden=false;document.body.classList.add("config-open")}
function closeConfig(){configPanel.classList.remove("is-open");configPanel.classList.add("is-hidden");configBackdrop.hidden=true;document.body.classList.remove("config-open")}
function normaliseCodes(v){return String(v||"").toUpperCase().split(/[\s,\-]+/).map(v=>v.trim()).filter(v=>/^[A-Z0-9]{1,4}$/.test(v))}
function checkboxCodes(){return [...form.querySelectorAll('input[name="meetingCode[]"]:checked')].map(i=>i.value.toUpperCase())}
function selectedCodes(){return [...new Set([...checkboxCodes(),...normaliseCodes(form.customCodes.value)])]}
function readConfig(){return{
showSelectableNextToJump:form.showSelectableNextToJump.checked,
showAllNextToJump:form.showAllNextToJump.checked,
showAllSecondNextToJump:form.showAllSecondNextToJump.checked,
showTripleResults:form.showTripleResults.checked,
showGallery:form.showGallery.checked,
meetingCodes:checkboxCodes(),customCodes:form.customCodes.value,
gridColumns:Number(form.gridColumns.value),panelHeight:Number(form.panelHeight.value),
showPanelHeading:form.showPanelHeading.checked}}
function writeConfig(c){
["showSelectableNextToJump","showAllNextToJump","showAllSecondNextToJump","showTripleResults","showGallery","showPanelHeading"].forEach(n=>{if(typeof c[n]==="boolean"&&form[n])form[n].checked=c[n]});
const codes=Array.isArray(c.meetingCodes)?c.meetingCodes:[];
form.querySelectorAll('input[name="meetingCode[]"]').forEach(i=>i.checked=codes.includes(i.value));
form.customCodes.value=c.customCodes||"";
if(c.gridColumns)form.gridColumns.value=String(c.gridColumns);
if(c.panelHeight)form.panelHeight.value=String(c.panelHeight)}
function selectableUrl(codes){const u=new URL(TAB_CONFIG.racingDetailUrl);u.searchParams.set("jurisdiction","SA");u.searchParams.set("channelType","retail");u.searchParams.set("page",codes.join("-"));return u.toString()}
function updatePreview(){const c=selectedCodes();document.getElementById("pageCodePreview").textContent=c.length?c.join("-"):"No codes selected"}
function card(title,url,c,subtitle){const el=document.createElement("section");el.className="display-card";el.style.setProperty("--panel-height",`${c.panelHeight}px`);
if(c.showPanelHeading){const h=document.createElement("div");h.className="display-card-header";h.innerHTML=`<div class="card-heading"><strong></strong><small></small></div>`;h.querySelector("strong").textContent=title;h.querySelector("small").textContent=subtitle;el.appendChild(h)}
const w=document.createElement("div");w.className="frame-wrap";const f=document.createElement("iframe");f.className="tab-frame";f.src=url;f.title=title;f.allow="fullscreen";f.loading="eager";w.appendChild(f);el.appendChild(w);return el}
function render(save=true){const c=readConfig(),codes=selectedCodes();if(save)localStorage.setItem(storageKey,JSON.stringify(c));displayGrid.replaceChildren();displayGrid.style.setProperty("--grid-columns",String(c.gridColumns));const p=[];
if(c.showSelectableNextToJump&&codes.length)p.push(["Selectable Next To Jump",selectableUrl(codes),`SA · page=${codes.join("-")}`]);
if(c.showAllNextToJump)p.push(["All Races Next To Jump",TAB_CONFIG.allNextToJumpUrl,"SA · page=0"]);
if(c.showAllSecondNextToJump)p.push(["All Second Next To Jump",TAB_CONFIG.allSecondNextToJumpUrl,"SA · page=1"]);
if(c.showTripleResults)p.push(["All Triple Race Results",TAB_CONFIG.tripleResultsUrl,"SA · fixed page"]);
if(c.showGallery)p.push(["Gallery with Triple Results",TAB_CONFIG.galleryUrl,"SA · fixed gallery"]);
p.forEach(x=>displayGrid.appendChild(card(x[0],x[1],c,x[2])));
if(c.showSelectableNextToJump&&!codes.length){const e=document.createElement("div");e.className="empty-state";e.textContent="Select at least one meeting code.";displayGrid.appendChild(e)}
if(!p.length&&!displayGrid.children.length){const e=document.createElement("div");e.className="empty-state";e.textContent="Select at least one TAB display.";displayGrid.appendChild(e)}
document.getElementById("displayTitle").textContent=`${p.length} TAB panel${p.length===1?"":"s"}`;updatePreview()}
async function fullscreenGrid(){if(document.fullscreenElement===displayGrid){await document.exitFullscreen();return}if(displayGrid.requestFullscreen)await displayGrid.requestFullscreen()}
form.addEventListener("submit",e=>{e.preventDefault();render(true);closeConfig()});
form.addEventListener("input",updatePreview);form.addEventListener("change",updatePreview);
document.querySelectorAll("[data-codes]").forEach(b=>b.addEventListener("click",()=>{const c=b.dataset.codes.split(",");form.querySelectorAll('input[name="meetingCode[]"]').forEach(i=>i.checked=c.includes(i.value));form.customCodes.value="";updatePreview()}));
document.getElementById("clearCodes").addEventListener("click",()=>{form.querySelectorAll('input[name="meetingCode[]"]').forEach(i=>i.checked=false);form.customCodes.value="";updatePreview()});
document.getElementById("fullscreenGrid").addEventListener("click",fullscreenGrid);
document.getElementById("hideConfig").addEventListener("click",closeConfig);
document.getElementById("showConfig").addEventListener("click",openConfig);
configBackdrop.addEventListener("click",closeConfig);
document.getElementById("resetConfig").addEventListener("click",()=>{localStorage.removeItem(storageKey);location.reload()});
const saved=localStorage.getItem(storageKey);if(saved){try{writeConfig(JSON.parse(saved))}catch(e){}}
closeConfig();updatePreview();render(false);
})();