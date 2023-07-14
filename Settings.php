<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\MailSignupDirectadmin;

use Aurora\System\SettingsProperty;

/**
 * @property bool $Disabled
 * @property string $DirectAdminURL
 * @property string $AdminUser
 * @property string $AdminPassword
 * @property int $UserDefaultQuotaMB
 */

class Settings extends \Aurora\System\Module\Settings
{
    protected function initDefaults()
    {
        $this->aContainer = [
            "Disabled" => new SettingsProperty(
                false,
                "bool",
                null,
                "Setting to true disables the module",
            ),
            "DirectAdminURL" => new SettingsProperty(
                "http://localhost:2222",
                "string",
                null,
                "Defines main URL of DirectAdmin installation",
            ),
            "AdminUser" => new SettingsProperty(
                "",
                "string",
                null,
                "Username of DirectAdmin administrator account",
            ),
            "AdminPassword" => new SettingsProperty(
                "",
                "string",
                null,
                "Password of DirectAdmin administrator account. Will be automatically encrypted.",
            ),
            "UserDefaultQuotaMB" => new SettingsProperty(
                20,
                "int",
                null,
                "Default quota of new email accounts created on DirectAdmin",
            ),
        ];
    }
}
