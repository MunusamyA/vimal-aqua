<?php
declare(strict_types=1);

if (!empty($GLOBALS['app_account_modal_rendered'])) {
    return;
}
$GLOBALS['app_account_modal_rendered'] = true;
?>
<form id="appAccountForm" class="app-modal-form hidden" data-component="account-form" novalidate>
    <input type="hidden" name="id" value="">

    <div class="modal-header">
        <div class="modal-header-copy">
            <h2 data-account-form-title>Add Account</h2>
            <p>Create or update an Aqua cash, bank, UPI, card or other account.</p>
        </div>
        <button class="modal-close" type="button" data-modal-close aria-label="Close Account form" title="Close">
            <i data-lucide="x"></i>
        </button>
    </div>

    <div class="modal-body">
        <div class="form-grid">
            <div class="field">
                <label for="appAccountCode">Account Code</label>
                <input id="appAccountCode" name="account_code" type="text" maxlength="30" readonly aria-readonly="true" placeholder="Auto generated">
            </div>

            <div class="field two-span">
                <label for="appAccountName" class="required">Account Name</label>
                <input id="appAccountName" name="account_name" type="text" maxlength="120" required
                       placeholder="Enter account name"
                       data-required-message="Account name is required.">
            </div>

            <div class="field">
                <label for="appAccountType" class="required">Account Type</label>
                <select id="appAccountType" name="account_type" required data-required-message="Account type is required.">
                    <option value="">Select account type</option>
                    <option value="1">Cash</option>
                    <option value="2">Bank</option>
                    <option value="3">UPI</option>
                    <option value="4">Card</option>
                    <option value="5">Other</option>
                </select>
            </div>

            <div class="field">
                <label for="appOpeningBalance">Opening Balance</label>
                <input id="appOpeningBalance" name="opening_balance" type="number" step="0.01" min="0" value="0.00" inputmode="decimal" placeholder="0.00">
            </div>

            <div class="field full">
                <label for="appAccountDescription">Description</label>
                <textarea id="appAccountDescription" name="description" rows="3" maxlength="255" placeholder="Enter description"></textarea>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <div class="buttons">
            <button class="btn gray" type="button" data-modal-close>Cancel</button>
            <button class="btn btn-primary" type="submit" data-account-save>Save Account</button>
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
        form = document.querySelector('[data-component="account-form"]');
        if (!form) return false;

        titleNode = form.querySelector("[data-account-form-title]");
        saveButton = form.querySelector("[data-account-save]");
        form.addEventListener("submit", submit);

        initialized = true;
        return true;
    }

    function reset() {
        if (!ensure()) return;
        form.reset();
        form.id.value = "";
        form.account_code.value = "";
        form.opening_balance.value = "0.00";
        if (window.Validation) Validation.clearForm(form);
    }

    function fill(row) {
        reset();
        form.id.value = row && row.id ? String(row.id) : "";
        form.account_code.value = row && row.account_code ? String(row.account_code) : "";
        form.account_name.value = row && row.account_name ? String(row.account_name) : "";
        form.account_type.value = row && row.account_type ? String(row.account_type) : "";
        form.opening_balance.value = row && row.opening_balance !== undefined && row.opening_balance !== null ? String(row.opening_balance) : "0.00";
        form.description.value = row && row.description ? String(row.description) : "";
    }

    function openModal(options) {
        if (!window.AppModal) {
            if (window.App) App.showError(null, "Common modal component is unavailable.");
            return false;
        }

        if (titleNode) titleNode.textContent = options.title || (options.mode === "edit" ? "Edit Account" : "Add Account");
        if (saveButton) saveButton.textContent = options.mode === "edit" ? "Update Account" : "Save Account";

        AppModal.open(form, {
            size: options.size || "lg",
            focusSelector: '[name="account_name"]',
            onClose: function (reason) {
                if (window.Validation) Validation.clearForm(form);
                var closedOptions = activeOptions;
                activeOptions = null;
                if (closedOptions && typeof closedOptions.onClosed === "function") {
                    closedOptions.onClosed(reason || "close");
                }
            }
        });

        if (window.lucide && typeof window.lucide.createIcons === "function") window.lucide.createIcons();
        return true;
    }

    async function open(options) {
        options = options || {};
        if (!ensure()) return false;

        activeOptions = options;
        var mode = String(options.mode || (options.id ? "edit" : "create")).toLowerCase();
        options.mode = mode;
        var apiUrl = options.apiUrl || "api/account.php";

        if (mode === "edit") {
            var id = Number(options.id || 0);
            if (!id) {
                App.showError(null, "Account record ID is required.");
                activeOptions = null;
                return false;
            }

            try {
                var result = await App.api(apiUrl + "?id=" + id);
                fill(result.data.account || {});
            } catch (error) {
                activeOptions = null;
                App.showError(error, "Unable to load Account record.");
                return false;
            }
        } else {
            reset();
            try {
                var optionsResult = await App.api(apiUrl + "?options=1");
                form.account_code.value = optionsResult.data.next_account_code || "";
            } catch (error) {
                activeOptions = null;
                App.showError(error, "Unable to load Account form.");
                return false;
            }
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
        var mode = id ? "edit" : "create";
        var body = {
            id: id || undefined,
            account_name: form.account_name.value.trim(),
            account_type: form.account_type.value,
            opening_balance: form.opening_balance.value || "0",
            description: form.description.value.trim()
        };

        saveButton.disabled = true;
        try {
            var result = await App.api((activeOptions && activeOptions.apiUrl) || "api/account.php", {
                method: id ? "PUT" : "POST",
                body: body
            });

            var account = result.data && result.data.account ? result.data.account : null;

            if (typeof window.showToast === "function") {
                showToast(result.message || "Account saved successfully.", { type: "success", duration: 2 });
            }

            var callback = activeOptions && typeof activeOptions.onSaved === "function" ? activeOptions.onSaved : null;

            window.dispatchEvent(new CustomEvent("app:account-saved", {
                detail: { account: account, mode: mode }
            }));

            if (!activeOptions || activeOptions.closeOnSaved !== false) {
                if (window.AppModal && AppModal.isOpen()) AppModal.close("saved");
            }

            if (callback) callback(account, result);
        } catch (error) {
            var applied = false;
            if (window.Validation && error && error.errors && Object.keys(error.errors).length) {
                applied = Validation.applyErrors(form, error.errors);
            }
            if (!applied) App.showError(error, "Unable to save Account record.");
        } finally {
            saveButton.disabled = false;
        }
    }

    function close() {
        if (window.AppModal && AppModal.isOpen()) return AppModal.close("programmatic");
        return false;
    }

    window.AppAccountForm = {
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
        close: close,
        reset: reset,
        getForm: function () { ensure(); return form; }
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", ensure);
    } else {
        ensure();
    }
})(window, document);
</script>
