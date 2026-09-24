<?php
declare(strict_types=1);

if (!empty($GLOBALS['app_hsn_modal_rendered'])) {
    return;
}
$GLOBALS['app_hsn_modal_rendered'] = true;
?>
<form id="appHsnForm" class="app-modal-form hidden" data-component="hsn-form" novalidate>
    <input type="hidden" name="id" value="">

    <div class="modal-header">
        <div class="modal-header-copy">
            <h2 data-hsn-form-title>Add HSN</h2>
            <p>Create or update HSN and GST rates.</p>
        </div>
        <button class="modal-close" type="button" data-modal-close aria-label="Close HSN form" title="Close">
            <i data-lucide="x"></i>
        </button>
    </div>

    <div class="modal-body">
        <div class="form-grid">
            <div class="field">
                <label for="appHsnCode" class="required">HSN Code</label>
                <input id="appHsnCode" name="hsn_code" type="text" maxlength="20" required
                       autocomplete="off" placeholder="Enter HSN code"
                       data-required-message="HSN Code is required.">
            </div>

            <div class="field two-span">
                <label for="appHsnDescription">Description</label>
                <input id="appHsnDescription" name="description" type="text" maxlength="180"
                       autocomplete="off" placeholder="Enter description">
            </div>

            <div class="field">
                <label for="appHsnGstRate" class="required">GST %</label>
                <input id="appHsnGstRate" name="gst_rate" type="text" inputmode="decimal" required
                       value="0.00" data-validation="decimal" data-decimal-places="2"
                       data-regex="^(?:[0-9]+(?:\.[0-9]{1,2})?|\.[0-9]{1,2})$"
                       data-required-message="GST % is required."
                       data-regex-message="Enter a valid GST %.">
            </div>

            <div class="field">
                <label for="appHsnCgstRate" class="required">CGST %</label>
                <input id="appHsnCgstRate" name="cgst_rate" type="text" inputmode="decimal" required
                       value="0.00" data-validation="decimal" data-decimal-places="2"
                       data-regex="^(?:[0-9]+(?:\.[0-9]{1,2})?|\.[0-9]{1,2})$"
                       data-required-message="CGST % is required."
                       data-regex-message="Enter a valid CGST %.">
            </div>

            <div class="field">
                <label for="appHsnSgstRate" class="required">SGST %</label>
                <input id="appHsnSgstRate" name="sgst_rate" type="text" inputmode="decimal" required
                       value="0.00" data-validation="decimal" data-decimal-places="2"
                       data-regex="^(?:[0-9]+(?:\.[0-9]{1,2})?|\.[0-9]{1,2})$"
                       data-required-message="SGST % is required."
                       data-regex-message="Enter a valid SGST %.">
            </div>

            <div class="field">
                <label for="appHsnIgstRate" class="required">IGST %</label>
                <input id="appHsnIgstRate" name="igst_rate" type="text" inputmode="decimal" required
                       value="0.00" data-validation="decimal" data-decimal-places="2"
                       data-regex="^(?:[0-9]+(?:\.[0-9]{1,2})?|\.[0-9]{1,2})$"
                       data-required-message="IGST % is required."
                       data-regex-message="Enter a valid IGST %.">
            </div>

            <div class="field">
                <label for="appHsnCessRate">Cess %</label>
                <input id="appHsnCessRate" name="cess_rate" type="text" inputmode="decimal"
                       value="0.00" data-validation="decimal" data-decimal-places="2"
                       data-regex="^(?:[0-9]+(?:\.[0-9]{1,2})?|\.[0-9]{1,2})$"
                       data-regex-message="Enter a valid Cess %.">
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <div class="buttons">
            <button class="btn gray" type="button" data-modal-close>Cancel</button>
            <button class="btn btn-primary" type="submit" data-hsn-save>Save HSN</button>
        </div>
    </div>
</form>

<script>
(function (window, document) {
    "use strict";

    var form = null;
    var titleNode = null;
    var saveButton = null;
    var activeOptions = null;
    var initialized = false;

    function ensure() {
        if (initialized) return Boolean(form);
        form = document.querySelector('[data-component="hsn-form"]');
        if (!form) return false;

        titleNode = form.querySelector("[data-hsn-form-title]");
        saveButton = form.querySelector("[data-hsn-save]");
        form.addEventListener("submit", submit);
        initialized = true;
        return true;
    }

    function reset() {
        if (!ensure()) return;
        form.reset();
        form.id.value = "";
        form.gst_rate.value = "0.00";
        form.cgst_rate.value = "0.00";
        form.sgst_rate.value = "0.00";
        form.igst_rate.value = "0.00";
        form.cess_rate.value = "0.00";
        if (window.Validation) Validation.clearForm(form);
    }

    function fill(row) {
        reset();
        form.id.value = row && row.id ? String(row.id) : "";
        form.hsn_code.value = row && row.hsn_code ? String(row.hsn_code) : "";
        form.description.value = row && row.description ? String(row.description) : "";
        form.gst_rate.value = Number(row && row.gst_rate || 0).toFixed(2);
        form.cgst_rate.value = Number(row && row.cgst_rate || 0).toFixed(2);
        form.sgst_rate.value = Number(row && row.sgst_rate || 0).toFixed(2);
        form.igst_rate.value = Number(row && row.igst_rate || 0).toFixed(2);
        form.cess_rate.value = Number(row && row.cess_rate || 0).toFixed(2);
    }

    function openModal(options) {
        if (!window.AppModal) {
            if (window.App) App.showError(null, "Common modal component is unavailable.");
            return false;
        }

        titleNode.textContent = options.title || (options.mode === "edit" ? "Edit HSN" : "Add HSN");
        saveButton.textContent = options.mode === "edit" ? "Update HSN" : "Save HSN";

        AppModal.open(form, {
            size: options.size || "lg",
            focusSelector: '[name="hsn_code"]',
            onClose: function (reason) {
                if (window.Validation) Validation.clearForm(form);
                var copy = activeOptions;
                activeOptions = null;
                if (copy && typeof copy.onClosed === "function") copy.onClosed(reason || "close");
            }
        });

        if (window.lucide) window.lucide.createIcons();
        return true;
    }

    async function open(options) {
        options = options || {};
        if (!ensure()) return false;

        activeOptions = options;
        var mode = String(options.mode || (options.id ? "edit" : "create")).toLowerCase();
        options.mode = mode;
        var apiUrl = options.apiUrl || "api/hsn.php";

        if (mode === "edit") {
            var id = Number(options.id || 0);
            if (!id) {
                App.showError(null, "HSN record ID is required.");
                activeOptions = null;
                return false;
            }

            try {
                var result = await App.api(apiUrl + "?id=" + id);
                fill(result.data.hsn || {});
            } catch (error) {
                activeOptions = null;
                App.showError(error, "Unable to load HSN record.");
                return false;
            }
        } else {
            reset();
        }

        return openModal(options);
    }

    async function submit(event) {
        event.preventDefault();

        if (window.Validation) {
            Validation.clearForm(form);
            if (!Validation.validateForm(form)) return;
        }

        var id = Number(form.id.value || 0);
        var body = {
            id: id || undefined,
            hsn_code: form.hsn_code.value.trim(),
            description: form.description.value.trim(),
            gst_rate: form.gst_rate.value.trim(),
            cgst_rate: form.cgst_rate.value.trim(),
            sgst_rate: form.sgst_rate.value.trim(),
            igst_rate: form.igst_rate.value.trim(),
            cess_rate: form.cess_rate.value.trim()
        };

        saveButton.disabled = true;
        try {
            var result = await App.api((activeOptions && activeOptions.apiUrl) || "api/hsn.php", {
                method: id ? "PUT" : "POST",
                body: body
            });

            var row = result.data && result.data.hsn ? result.data.hsn : null;

            if (window.showToast) {
                showToast(result.message || "HSN saved successfully.", {type:"success",duration:2});
            }

            var callback = activeOptions && typeof activeOptions.onSaved === "function"
                ? activeOptions.onSaved
                : null;

            window.dispatchEvent(new CustomEvent("app:hsn-saved", {
                detail: {hsn: row, mode: id ? "edit" : "create"}
            }));

            if (!activeOptions || activeOptions.closeOnSaved !== false) {
                if (window.AppModal && AppModal.isOpen()) AppModal.close("saved");
            }

            if (callback) callback(row, result);
        } catch (error) {
            var applied = false;
            if (window.Validation && error && error.errors && Object.keys(error.errors).length) {
                applied = Validation.applyErrors(form, error.errors);
            }
            if (!applied) App.showError(error, "Unable to save HSN record.");
        } finally {
            saveButton.disabled = false;
        }
    }

    window.AppHSNForm = {
        open: open,
        openCreate: function (options) {
            options = options || {};
            options.mode = "create";
            return open(options);
        },
        openEdit: function (id, options) {
            options = options || {};
            options.mode = "edit";
            options.id = id;
            return open(options);
        },
        close: function () {
            return window.AppModal && AppModal.isOpen() ? AppModal.close("programmatic") : false;
        },
        reset: reset,
        getForm: function () {
            ensure();
            return form;
        }
    };

    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", ensure);
    else ensure();
})(window, document);
</script>
