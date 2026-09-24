<?php
declare(strict_types=1);
if (!empty($GLOBALS['app_price_level_modal_rendered'])) return;
$GLOBALS['app_price_level_modal_rendered'] = true;
?>
<form id="appPriceLevelForm" class="app-modal-form hidden" data-component="price-level-form" novalidate>
    <input type="hidden" name="id" value="">
    <div class="modal-header">
        <div class="modal-header-copy"><h2 data-price-level-form-title>Add Price Level</h2><p>Create or update a customer sales price level.</p></div>
        <button class="modal-close" type="button" data-modal-close aria-label="Close Price Level form" title="Close"><i data-lucide="x"></i></button>
    </div>
    <div class="modal-body"><div class="form-grid">
        <div class="field full"><label for="appPriceLevelName" class="required">Price Level Name</label><input id="appPriceLevelName" name="price_level_name" maxlength="100" required placeholder="e.g. Retail" data-required-message="Price level name is required."></div>
    </div></div>
    <div class="modal-footer"><div class="buttons"><button class="btn gray" type="button" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit" data-price-level-save>Save Price Level</button></div></div>
</form>
<script>
(function(window,document){"use strict";
var form=null,titleNode=null,saveButton=null,activeOptions=null,initialized=false;
function ensure(){if(initialized)return Boolean(form);form=document.querySelector('[data-component="price-level-form"]');if(!form)return false;titleNode=form.querySelector('[data-price-level-form-title]');saveButton=form.querySelector('[data-price-level-save]');form.addEventListener('submit',submit);initialized=true;return true;}
function reset(){if(!ensure())return;form.reset();form.id.value="";if(window.Validation)Validation.clearForm(form);}
function fill(row){reset();form.id.value=row&&row.id?String(row.id):"";form.price_level_name.value=row&&row.price_level_name?String(row.price_level_name):"";}
function openModal(options){if(!window.AppModal){if(window.App)App.showError(null,'Common modal component is unavailable.');return false;}titleNode.textContent=options.title||(options.mode==='edit'?'Edit Price Level':'Add Price Level');saveButton.textContent=options.mode==='edit'?'Update Price Level':'Save Price Level';AppModal.open(form,{size:options.size||'md',focusSelector:'[name="price_level_name"]',onClose:function(reason){if(window.Validation)Validation.clearForm(form);var x=activeOptions;activeOptions=null;if(x&&typeof x.onClosed==='function')x.onClosed(reason||'close');}});if(window.lucide)window.lucide.createIcons();return true;}
async function open(options){options=options||{};if(!ensure())return false;activeOptions=options;var mode=String(options.mode||(options.id?'edit':'create')).toLowerCase();options.mode=mode;var apiUrl=options.apiUrl||'api/price-level.php';if(mode==='edit'){var id=Number(options.id||0);if(!id){App.showError(null,'Price Level record ID is required.');activeOptions=null;return false;}try{var result=await App.api(apiUrl+'?id='+id);fill(result.data.price_level||{});}catch(error){activeOptions=null;App.showError(error,'Unable to load Price Level record.');return false;}}else reset();return openModal(options);}
async function submit(event){event.preventDefault();if(window.Validation){Validation.clearForm(form);if(!Validation.validateForm(form))return;}var id=Number(form.id.value||0);var body={id:id||undefined,price_level_name:form.price_level_name.value.trim()};saveButton.disabled=true;try{var result=await App.api((activeOptions&&activeOptions.apiUrl)||'api/price-level.php',{method:id?'PUT':'POST',body:body});var row=result.data&&result.data.price_level?result.data.price_level:null;if(window.showToast)showToast(result.message||'Price Level saved successfully.',{type:'success',duration:2});var callback=activeOptions&&typeof activeOptions.onSaved==='function'?activeOptions.onSaved:null;window.dispatchEvent(new CustomEvent('app:price-level-saved',{detail:{price_level:row,mode:id?'edit':'create'}}));if(!activeOptions||activeOptions.closeOnSaved!==false){if(window.AppModal&&AppModal.isOpen())AppModal.close('saved');}if(callback)callback(row,result);}catch(error){var applied=false;if(window.Validation&&error&&error.errors&&Object.keys(error.errors).length)applied=Validation.applyErrors(form,error.errors);if(!applied)App.showError(error,'Unable to save Price Level record.');}finally{saveButton.disabled=false;}}
window.AppPriceLevelForm={open:open,openCreate:function(o){o=o||{};o.mode='create';return open(o);},openEdit:function(id,o){o=o||{};o.mode='edit';o.id=id;return open(o);},close:function(){return window.AppModal&&AppModal.isOpen()?AppModal.close('programmatic'):false;},reset:reset,getForm:function(){ensure();return form;}};
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',ensure);else ensure();
})(window,document);
</script>
