<?php
declare(strict_types=1);
if (!empty($GLOBALS['app_unit_modal_rendered'])) return;
$GLOBALS['app_unit_modal_rendered'] = true;
?>
<form id="appUnitForm" class="app-modal-form hidden" data-component="unit-form" novalidate>
    <input type="hidden" name="id" value="">
    <div class="modal-header">
        <div class="modal-header-copy"><h2 data-unit-form-title>Add Unit</h2><p>Create or update a product unit.</p></div>
        <button class="modal-close" type="button" data-modal-close aria-label="Close Unit form" title="Close"><i data-lucide="x"></i></button>
    </div>
    <div class="modal-body"><div class="form-grid">
        <div class="field two-span"><label for="appUnitName" class="required">Unit Name</label><input id="appUnitName" name="unit_name" maxlength="80" required placeholder="e.g. Piece" data-required-message="Unit name is required."></div>
        <div class="field"><label for="appUnitShortName" class="required">Short Name</label><input id="appUnitShortName" name="short_name" maxlength="20" required placeholder="e.g. PCS" data-required-message="Short name is required."></div>
    </div></div>
    <div class="modal-footer"><div class="buttons"><button class="btn gray" type="button" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit" data-unit-save>Save Unit</button></div></div>
</form>
<script>
(function(window,document){"use strict";
var form=null,titleNode=null,saveButton=null,activeOptions=null,initialized=false;
function ensure(){if(initialized)return Boolean(form);form=document.querySelector('[data-component="unit-form"]');if(!form)return false;titleNode=form.querySelector('[data-unit-form-title]');saveButton=form.querySelector('[data-unit-save]');form.addEventListener('submit',submit);initialized=true;return true;}
function reset(){if(!ensure())return;form.reset();form.id.value="";if(window.Validation)Validation.clearForm(form);}
function fill(row){reset();form.id.value=row&&row.id?String(row.id):"";form.unit_name.value=row&&row.unit_name?String(row.unit_name):"";form.short_name.value=row&&row.short_name?String(row.short_name):"";}
function openModal(options){if(!window.AppModal){if(window.App)App.showError(null,"Common modal component is unavailable.");return false;}titleNode.textContent=options.title||(options.mode==='edit'?'Edit Unit':'Add Unit');saveButton.textContent=options.mode==='edit'?'Update Unit':'Save Unit';AppModal.open(form,{size:options.size||'md',focusSelector:'[name="unit_name"]',onClose:function(reason){if(window.Validation)Validation.clearForm(form);var x=activeOptions;activeOptions=null;if(x&&typeof x.onClosed==='function')x.onClosed(reason||'close');}});if(window.lucide)window.lucide.createIcons();return true;}
async function open(options){options=options||{};if(!ensure())return false;activeOptions=options;var mode=String(options.mode||(options.id?'edit':'create')).toLowerCase();options.mode=mode;var apiUrl=options.apiUrl||'api/unit.php';if(mode==='edit'){var id=Number(options.id||0);if(!id){App.showError(null,'Unit record ID is required.');activeOptions=null;return false;}try{var result=await App.api(apiUrl+'?id='+id);fill(result.data.unit||{});}catch(error){activeOptions=null;App.showError(error,'Unable to load Unit record.');return false;}}else reset();return openModal(options);}
async function submit(event){event.preventDefault();if(window.Validation){Validation.clearForm(form);if(!Validation.validateForm(form))return;}var id=Number(form.id.value||0);var body={id:id||undefined,unit_name:form.unit_name.value.trim(),short_name:form.short_name.value.trim()};saveButton.disabled=true;try{var result=await App.api((activeOptions&&activeOptions.apiUrl)||'api/unit.php',{method:id?'PUT':'POST',body:body});var row=result.data&&result.data.unit?result.data.unit:null;if(window.showToast)showToast(result.message||'Unit saved successfully.',{type:'success',duration:2});var callback=activeOptions&&typeof activeOptions.onSaved==='function'?activeOptions.onSaved:null;window.dispatchEvent(new CustomEvent('app:unit-saved',{detail:{unit:row,mode:id?'edit':'create'}}));if(!activeOptions||activeOptions.closeOnSaved!==false){if(window.AppModal&&AppModal.isOpen())AppModal.close('saved');}if(callback)callback(row,result);}catch(error){var applied=false;if(window.Validation&&error&&error.errors&&Object.keys(error.errors).length)applied=Validation.applyErrors(form,error.errors);if(!applied)App.showError(error,'Unable to save Unit record.');}finally{saveButton.disabled=false;}}
window.AppUnitForm={open:open,openCreate:function(o){o=o||{};o.mode='create';return open(o);},openEdit:function(id,o){o=o||{};o.mode='edit';o.id=id;return open(o);},close:function(){return window.AppModal&&AppModal.isOpen()?AppModal.close('programmatic'):false;},reset:reset,getForm:function(){ensure();return form;}};
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',ensure);else ensure();
})(window,document);
</script>
