<?php
require_once __DIR__ . '/include/web-config.php';
$pageTitle = 'Vehicle Form';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="<?php echo web_h(app_theme_color()); ?>">
    <title><?php echo web_h((string)($pageTitle ?? app_name())); ?> · <?php echo web_h(app_name()); ?></title>
    <?php render_frontend_config_script(); ?>
    <script src="assets/js/runtime.js"></script>
    <?php foreach ((isset($headStyles) && is_array($headStyles) ? $headStyles : []) as $styleUrl): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((string)$styleUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
    <?php foreach ((isset($headScripts) && is_array($headScripts) ? $headScripts : []) as $scriptUrl): ?>
    <script src="<?php echo htmlspecialchars((string)$scriptUrl, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php endforeach; ?>
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/include/sidebar.php'; ?>
    <main class="main-stage">
<?php require __DIR__ . '/include/topbar.php'; ?>
        <section class="page-content">
<script src="assets/js/toaster.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/theme.js"></script>
<script src="assets/js/layout.js"></script>

<div class="page-head">
    <h1 id="pageHeading">Add Vehicle</h1>
    <a class="btn gray" href="vehicle-list.php">Vehicle List</a>
</div>

<div class="card form-card">
    <form id="vehicleForm" novalidate>
        <input type="hidden" name="ref" id="vehicleRef">

        <div class="card-header">
            <div>
                <h2>Vehicle Details</h2>
                <p>Add or update a delivery vehicle for this branch.</p>
            </div>
        </div>

        <div class="card-body">
            <div class="form-grid">
                <div class="field">
                    <label for="vehicleNo" class="required">Vehicle Number</label>
                    <input id="vehicleNo" name="vehicle_no" type="text" maxlength="30" required
                           autocomplete="off" placeholder="Example: TN70AB1234"
                           data-required-message="Vehicle number is required.">
                </div>

                <div class="field">
                    <label for="vehicleName">Vehicle Name</label>
                    <input id="vehicleName" name="vehicle_name" type="text" maxlength="100"
                           autocomplete="off" placeholder="Example: Tata Ace">
                </div>
            </div>
        </div>

        <div class="card-footer">
            <div class="buttons">
                <button class="btn btn-primary" id="saveButton" type="submit">Save Vehicle</button>
                <a class="btn gray" href="vehicle-list.php">Cancel</a>
            </div>
        </div>
    </form>
</div>

<script src="assets/js/validation.js"></script>
<script>
(function (window, document) {
    "use strict";

    var form = document.getElementById("vehicleForm");
    var reference = new URLSearchParams(location.search).get("ref") || "";
    var saveButton = document.getElementById("saveButton");
    var isLoading = false;

    function hasAction(actions, id) {
        return (actions || []).map(Number).indexOf(Number(id)) !== -1;
    }

    function fillVehicle(vehicle) {
        document.getElementById("vehicleRef").value = vehicle.ref || "";
        form.vehicle_no.value = vehicle.vehicle_no || "";
        form.vehicle_name.value = vehicle.vehicle_name || "";
    }

    async function load() {
        if (isLoading) return;
        isLoading = true;

        try {
            if (reference) {
                document.getElementById("pageHeading").textContent = "Edit Vehicle";
                saveButton.textContent = "Update Vehicle";

                var result = await App.api("api/vehicles.php?ref=" + encodeURIComponent(reference));
                fillVehicle(result.data.vehicle || {});

                if (!hasAction(result.data.allowed_actions, 3)) {
                    saveButton.disabled = true;
                }
            } else {
                var optionsResult = await App.api("api/vehicles.php?options=1");
                if (!hasAction(optionsResult.data.allowed_actions, 2)) {
                    saveButton.disabled = true;
                }
            }
        } catch (error) {
            App.showError(error, "Unable to load vehicle form.");
            saveButton.disabled = true;
        } finally {
            isLoading = false;
        }
    }

    form.vehicle_no.addEventListener("blur", function () {
        this.value = this.value.trim().toUpperCase();
    });

    form.addEventListener("submit", async function (event) {
        event.preventDefault();

        if (window.Validation) {
            Validation.clearForm(form);
            if (!Validation.validateForm(form)) return;
        }

        var data = new FormData(form);
        data.set("vehicle_no", form.vehicle_no.value.trim().toUpperCase());
        data.set("vehicle_name", form.vehicle_name.value.trim());
        if (reference) data.set("_method", "PUT");

        saveButton.disabled = true;
        try {
            var result = await App.api("api/vehicles.php", {
                method: "POST",
                body: data
            });

            if (typeof window.showToast === "function") {
                showToast(result.message || "Vehicle saved successfully.", { type:"success", duration:2 });
            }

            setTimeout(function () {
                location.href = "vehicle-list.php";
            }, 700);
        } catch (error) {
            if (window.Validation) Validation.applyErrors(form, error.errors || {});
            App.showError(error, "Unable to save vehicle.");
            saveButton.disabled = false;
        }
    });

    load();
})(window, document);
</script>
        </section>
<?php require __DIR__ . '/include/footer.php'; ?>
    </main>
</div>
<script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
