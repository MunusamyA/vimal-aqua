<?php
declare(strict_types=1);
if (!empty($GLOBALS['app_category_modal_rendered'])) return;
$GLOBALS['app_category_modal_rendered'] = true;
?>
<form id="appCategoryForm" class="app-modal-form hidden" data-component="category-form" novalidate>
    <input type="hidden" name="id" value="">
    <div class="modal-header">
        <div class="modal-header-copy"><h2 data-category-form-title>Add Category</h2><p>Create or update an Aqua product category.</p></div>
        <button class="modal-close" type="button" data-modal-close aria-label="Close Category form" title="Close"><i data-lucide="x"></i></button>
    </div>
    <div class="modal-body"><div class="form-grid">
        <div class="field"><label for="appCategoryCode">Category Code</label><input id="appCategoryCode" name="category_code" maxlength="30" readonly aria-readonly="true" placeholder="Auto generated"></div>
        <div class="field two-span"><label for="appCategoryName" class="required">Category Name</label><input id="appCategoryName" name="category_name" maxlength="120" required placeholder="Enter category name" data-required-message="Category name is required."></div>
        <div class="field full"><label for="appCategoryDescription">Description</label><textarea id="appCategoryDescription" name="description" rows="3" maxlength="255" placeholder="Enter description"></textarea></div>
    </div></div>
    <div class="modal-footer"><div class="buttons"><button class="btn gray" type="button" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit" data-category-save>Save Category</button></div></div>
</form>
<script>
(function(window,document){"use strict";
var form=null,titleNode=null,saveButton=null,activeOptions=null,initialized=false;
function ensure(){if(initialized)return Boolean(form);form=document.querySelector('[data-component="category-form"]');if(!form)return false;titleNode=form.querySelector('[data-category-form-title]');saveButton=form.querySelector('[data-category-save]');form.addEventListener('submit',submit);initialized=true;return true;}
function reset(){if(!ensure())return;form.reset();form.id.value="";form.category_code.value="";if(window.Validation)Validation.clearForm(form);}
function fill(row){reset();form.id.value=row&&row.id?String(row.id):"";form.category_code.value=row&&row.category_code?String(row.category_code):"";form.category_name.value=row&&row.category_name?String(row.category_name):"";form.description.value=row&&row.description?String(row.description):"";}
function openModal(options){if(!window.AppModal){if(window.App)App.showError(null,'Common modal component is unavailable.');return false;}titleNode.textContent=options.title||(options.mode==='edit'?'Edit Category':'Add Category');saveButton.textContent=options.mode==='edit'?'Update Category':'Save Category';AppModal.open(form,{size:options.size||'lg',focusSelector:'[name="category_name"]',onClose:function(reason){if(window.Validation)Validation.clearForm(form);var x=activeOptions;activeOptions=null;if(x&&typeof x.onClosed==='function')x.onClosed(reason||'close');}});if(window.lucide)window.lucide.createIcons();return true;}
async function open(options){options=options||{};if(!ensure())return false;activeOptions=options;var mode=String(options.mode||(options.id?'edit':'create')).toLowerCase();options.mode=mode;var apiUrl=options.apiUrl||'api/category.php';if(mode==='edit'){var id=Number(options.id||0);if(!id){App.showError(null,'Category record ID is required.');activeOptions=null;return false;}try{var r=await App.api(apiUrl+'?id='+id);fill(r.data.category||{});}catch(error){activeOptions=null;App.showError(error,'Unable to load Category record.');return false;}}else{reset();try{var r2=await App.api(apiUrl+'?options=1');form.category_code.value=r2.data.next_category_code||'';}catch(error){activeOptions=null;App.showError(error,'Unable to load Category form.');return false;}}return openModal(options);}
async function submit(event){event.preventDefault();if(window.Validation){Validation.clearForm(form);if(!Validation.validateForm(form))return;}var id=Number(form.id.value||0);var body={id:id||undefined,category_name:form.category_name.value.trim(),description:form.description.value.trim()};saveButton.disabled=true;try{var result=await App.api((activeOptions&&activeOptions.apiUrl)||'api/category.php',{method:id?'PUT':'POST',body:body});var row=result.data&&result.data.category?result.data.category:null;if(window.showToast)showToast(result.message||'Category saved successfully.',{type:'success',duration:2});var callback=activeOptions&&typeof activeOptions.onSaved==='function'?activeOptions.onSaved:null;window.dispatchEvent(new CustomEvent('app:category-saved',{detail:{category:row,mode:id?'edit':'create'}}));if(!activeOptions||activeOptions.closeOnSaved!==false){if(window.AppModal&&AppModal.isOpen())AppModal.close('saved');}if(callback)callback(row,result);}catch(error){var applied=false;if(window.Validation&&error&&error.errors&&Object.keys(error.errors).length)applied=Validation.applyErrors(form,error.errors);if(!applied)App.showError(error,'Unable to save Category record.');}finally{saveButton.disabled=false;}}
window.AppCategoryForm={open:open,openCreate:function(o){o=o||{};o.mode='create';return open(o);},openEdit:function(id,o){o=o||{};o.mode='edit';o.id=id;return open(o);},close:function(){return window.AppModal&&AppModal.isOpen()?AppModal.close('programmatic'):false;},reset:reset,getForm:function(){ensure();return form;}};
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',ensure);else ensure();
})(window,document);
</script>
