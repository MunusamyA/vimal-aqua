-- PHP ERP Starter Kit v3 -> v4 semantic theme migration
-- Run only when upgrading an existing Starter Kit v3 database.
-- Fresh installations should use database/starterkit_fresh.sql instead.

UPDATE app_settings SET setting_key='theme_primary_deep'   WHERE setting_key='theme_brand_950';
UPDATE app_settings SET setting_key='theme_primary_strong' WHERE setting_key='theme_brand_900';
UPDATE app_settings SET setting_key='theme_primary'        WHERE setting_key='theme_brand_800';
UPDATE app_settings SET setting_key='theme_primary_medium' WHERE setting_key='theme_brand_700';
UPDATE app_settings SET setting_key='theme_primary_light'  WHERE setting_key='theme_brand_600';
UPDATE app_settings SET setting_key='theme_primary_soft'   WHERE setting_key='theme_brand_100';
UPDATE app_settings SET setting_key='theme_primary_subtle' WHERE setting_key='theme_brand_50';
UPDATE app_settings SET setting_key='theme_accent'         WHERE setting_key='theme_gold_500';
UPDATE app_settings SET setting_key='theme_accent_teal'    WHERE setting_key='theme_teal_500';
UPDATE app_settings SET setting_key='theme_info'           WHERE setting_key='theme_blue_500';
UPDATE app_settings SET setting_key='theme_warning'        WHERE setting_key='theme_orange_500';
UPDATE app_settings SET setting_key='theme_danger'         WHERE setting_key='theme_red_500';
UPDATE app_settings SET setting_key='theme_text_primary'   WHERE setting_key='theme_ink_900';
UPDATE app_settings SET setting_key='theme_text_secondary' WHERE setting_key='theme_ink_700';
UPDATE app_settings SET setting_key='theme_text_muted'     WHERE setting_key='theme_ink_500';
UPDATE app_settings SET setting_key='theme_border'         WHERE setting_key='theme_line';
