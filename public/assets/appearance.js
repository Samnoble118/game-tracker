/** Provides live theme previews and WCAG contrast feedback. */
document.addEventListener('DOMContentLoaded', () => {
    const form=document.querySelector('[data-theme-form]'); const preview=document.querySelector('[data-theme-preview]'); const status=document.querySelector('[data-contrast-status]');
    if(!form||!preview||!status)return;
    const fields={accent:form.elements.theme_accent,background:form.elements.theme_background,panel:form.elements.theme_panel,text:form.elements.theme_text};
    const page=document.body;
    const luminance=(hex)=>{const values=[1,3,5].map((start)=>parseInt(hex.slice(start,start+2),16)/255).map((value)=>value<=.04045?value/12.92:((value+.055)/1.055)**2.4);return .2126*values[0]+.7152*values[1]+.0722*values[2];};
    const contrast=(a,b)=>{const values=[luminance(a),luminance(b)].sort((x,y)=>y-x);return (values[0]+.05)/(values[1]+.05);};
    const update=()=>{preview.style.setProperty('--preview-accent',fields.accent.value);preview.style.setProperty('--preview-bg',fields.background.value);preview.style.setProperty('--preview-panel',fields.panel.value);preview.style.setProperty('--preview-text',fields.text.value);page.style.setProperty('--accent',fields.accent.value);page.style.setProperty('--bg',fields.background.value);page.style.setProperty('--panel',fields.panel.value);page.style.setProperty('--text',fields.text.value);const lowest=Math.min(contrast(fields.text.value,fields.background.value),contrast(fields.text.value,fields.panel.value));status.textContent=lowest>=4.5?`Live page preview · readable contrast: ${lowest.toFixed(1)}:1 ✓`:`Live page preview · contrast ${lowest.toFixed(1)}:1 — adjust colours to reach 4.5:1`;status.classList.toggle('is-valid',lowest>=4.5);status.classList.toggle('is-invalid',lowest<4.5);};
    form.querySelectorAll('[data-preset]').forEach((radio)=>radio.addEventListener('change',()=>{if(!radio.checked)return;const preset=JSON.parse(radio.dataset.preset);Object.keys(fields).forEach((key)=>fields[key].value=preset[key]);update();}));
    Object.values(fields).forEach((field)=>field.addEventListener('input',()=>{form.querySelector('[value="custom"]').checked=true;update();})); update();
});
