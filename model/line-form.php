<?php
declare(strict_types=1);
if (!empty($GLOBALS['app_line_modal_rendered'])) return;
$GLOBALS['app_line_modal_rendered'] = true;
?>
<form id="appLineForm" class="app-modal-form hidden" data-component="line-form" novalidate>
    <input type="hidden" name="id" value="">
    <div class="modal-header">
        <div class="modal-header-copy"><h2 data-line-form-title>Add Line</h2><p>Create or update a customer delivery line.</p></div>
        <button class="modal-close" type="button" data-modal-close aria-label="Close Line form" title="Close"><i data-lucide="x"></i></button>
    </div>
    <div class="modal-body"><div class="form-grid">
        <div class="field"><label for="appLineCode">Line Code</label><input id="appLineCode" name="line_code" maxlength="30" readonly aria-readonly="true" placeholder="Auto generated"></div>
        <div class="field two-span"><label for="appLineName" class="required">Line Name</label><input id="appLineName" name="line_name" maxlength="120" required placeholder="Enter line name" data-required-message="Line name is required."></div>
    </div></div>
    <div class="modal-footer"><div class="buttons"><button class="btn gray" type="button" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit" data-line-save>Save Line</button></div></div>
</form>
<script>
(function(window,document){"use strict";
var form=null,titleNode=null,saveButton=null,activeOptions=null,initialized=false;
function ensure(){if(initialized)return Boolean(form);form=document.querySelector('[data-component="line-form"]');if(!form)return false;titleNode=form.querySelector('[data-line-form-title]');saveButton=form.querySelector('[data-line-save]');form.addEventListener('submit',submit);initialized=true;return true;}
function reset(){if(!ensure())return;form.reset();form.id.value="";form.line_code.value="";if(window.Validation)Validation.clearForm(form);}
function fill(row){reset();form.id.value=row&&row.id?String(row.id):"";form.line_code.value=row&&row.line_code?String(row.line_code):"";form.line_name.value=row&&row.line_name?String(row.line_name):"";}
function openModal(options){if(!window.AppModal){if(window.App)App.showError(null,'Common modal component is unavailable.');return false;}titleNode.textContent=options.title||(options.mode==='edit'?'Edit Line':'Add Line');saveButton.textContent=options.mode==='edit'?'Update Line':'Save Line';AppModal.open(form,{size:options.size||'md',focusSelector:'[name="line_name"]',onClose:function(reason){if(window.Validation)Validation.clearForm(form);var x=activeOptions;activeOptions=null;if(x&&typeof x.onClosed==='function')x.onClosed(reason||'close');}});if(window.lucide)window.lucide.createIcons();return true;}
async function open(options){options=options||{};if(!ensure())return false;activeOptions=options;var mode=String(options.mode||(options.id?'edit':'create')).toLowerCase();options.mode=mode;var apiUrl=options.apiUrl||'api/line.php';if(mode==='edit'){var id=Number(options.id||0);if(!id){App.showError(null,'Line record ID is required.');activeOptions=null;return false;}try{var r=await App.api(apiUrl+'?id='+id);fill(r.data.line||{});}catch(error){activeOptions=null;App.showError(error,'Unable to load Line record.');return false;}}else{reset();try{var r2=await App.api(apiUrl+'?options=1');form.line_code.value=r2.data.next_line_code||'';}catch(error){activeOptions=null;App.showError(error,'Unable to load Line form.');return false;}}return openModal(options);}
async function submit(event){event.preventDefault();if(window.Validation){Validation.clearForm(form);if(!Validation.validateForm(form))return;}var id=Number(form.id.value||0);var body={id:id||undefined,line_name:form.line_name.value.trim()};saveButton.disabled=true;try{var result=await App.api((activeOptions&&activeOptions.apiUrl)||'api/line.php',{method:id?'PUT':'POST',body:body});var row=result.data&&result.data.line?result.data.line:null;if(window.showToast)showToast(result.message||'Line saved successfully.',{type:'success',duration:2});var callback=activeOptions&&typeof activeOptions.onSaved==='function'?activeOptions.onSaved:null;window.dispatchEvent(new CustomEvent('app:line-saved',{detail:{line:row,mode:id?'edit':'create'}}));if(!activeOptions||activeOptions.closeOnSaved!==false){if(window.AppModal&&AppModal.isOpen())AppModal.close('saved');}if(callback)callback(row,result);}catch(error){var applied=false;if(window.Validation&&error&&error.errors&&Object.keys(error.errors).length)applied=Validation.applyErrors(form,error.errors);if(!applied)App.showError(error,'Unable to save Line record.');}finally{saveButton.disabled=false;}}
window.AppLineForm={open:open,openCreate:function(o){o=o||{};o.mode='create';return open(o);},openEdit:function(id,o){o=o||{};o.mode='edit';o.id=id;return open(o);},close:function(){return window.AppModal&&AppModal.isOpen()?AppModal.close('programmatic'):false;},reset:reset,getForm:function(){ensure();return form;}};
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',ensure);else ensure();
})(window,document);
</script>
