<?php

/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\MailSignupDirectadmin;

/**
 * Allows users to create new email accounts for themselves on DirectAdmin.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @property Settings $oModuleSettings
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
    /**
     * @var \DirectAdminSignAPI
     */
    private $oDAApi;

    /**
     * @return Module
     */
    public static function getInstance()
    {
        return parent::getInstance();
    }

    /**
     * @return Module
     */
    public static function Decorator()
    {
        return parent::Decorator();
    }

    public function init()
    {
        $this->subscribeEvent('MailSignup::Signup::before', [$this, 'onAfterSignup']);

        require_once __DIR__ . '/da_api_sign.php';

        $sDaURL = $this->oModuleSettings->DirectAdminURL;
        $sDaAdminUser = $this->oModuleSettings->AdminUser;
        $sDaAdminPassword = $this->oModuleSettings->AdminPassword;
        if ($sDaAdminPassword && !\Aurora\System\Utils::IsEncryptedValue($sDaAdminPassword)) {
            $this->setConfig('AdminPassword', \Aurora\System\Utils::EncryptValue($sDaAdminPassword));
            $this->saveModuleConfig();
        } else {
            $sDaAdminPassword = \Aurora\System\Utils::DecryptValue($this->oModuleSettings->AdminPassword);
        }
        $iPos = strpos($sDaURL, '://');
        $sDaFullURL = substr($sDaURL, 0, $iPos + 3) . $sDaAdminUser . ':' . $sDaAdminPassword . '@' . substr($sDaURL, $iPos + 3);
        $this->oDAApi = new \DirectAdminSignAPI($sDaFullURL);
    }

    /**
     * Creates account with credentials specified in registration form
     *
     * @param array $aArgs New account credentials.
     * @param mixed $mResult Is passed by reference.
     */
    public function onAfterSignup($aArgs, &$mResult)
    {
        if (isset($aArgs['Login']) && isset($aArgs['Password']) && !empty(trim($aArgs['Password'])) && !empty(trim($aArgs['Login']))) {
            $sLogin = trim($aArgs['Login']);
            $sPassword = trim($aArgs['Password']);
            $sFriendlyName = isset($aArgs['Name']) ? trim($aArgs['Name']) : '';
            $bSignMe = isset($aArgs['SignMe']) ? (bool) $aArgs['SignMe'] : false;
            $iQuota = (int) $this->oModuleSettings->UserDefaultQuotaMB;

            $bPrevState = \Aurora\System\Api::skipCheckUserRole(true);
            [$sUsername, $sDomain] = explode("@", $sLogin);
            if (!empty($sDomain)) {
                $aResult = array();
                try {
                    $mResultDA = $this->oDAApi->CMD_API_POP("create", $sDomain, $sUsername, $sPassword, $sPassword, $iQuota, '');
                    parse_str(urldecode($mResultDA), $aResult);
                    \Aurora\System\Api::Log('API call result:\n' . $mResultDA, \Aurora\System\Enums\LogLevel::Full);
                } catch(\Exception $oException) {
                    throw new \Aurora\System\Exceptions\ApiException(0, $oException, $oException->getMessage());
                }
                if (is_array($aResult) && isset($aResult['error']) && ($aResult['error'] != "1")) {
                    $iUserId = \Aurora\Modules\Core\Module::Decorator()->CreateUser(0, $sLogin);
                    $oUser = \Aurora\System\Api::getUserById((int) $iUserId);
                    try {
                        $oAccount = \Aurora\Modules\Mail\Module::Decorator()->CreateAccount($oUser->Id, $sFriendlyName, $sLogin, $sLogin, $sPassword);
                        if ($oAccount instanceof \Aurora\Modules\Mail\Models\MailAccount) {
                            $iTime = $bSignMe ? 0 : time();
                            $sAuthToken = \Aurora\System\Api::UserSession()->Set(
                                [
                                    'token'		=> 'auth',
                                    'sign-me'		=> $bSignMe,
                                    'id'			=> $oAccount->IdUser,
                                    'account'		=> $oAccount->Id,
                                    'account_type'	=> $oAccount->getName()
                                ],
                                $iTime
                            );
                            $mResult = ['AuthToken' => $sAuthToken];
                        }
                    } catch (\Exception $oException) {
                        if ($oException instanceof \Aurora\Modules\Mail\Exceptions\Exception &&
                            $oException->getCode() === \Aurora\Modules\Mail\Enums\ErrorCodes::CannotLoginCredentialsIncorrect) {
                            \Aurora\Modules\Core\Module::Decorator()->DeleteUser($oUser->Id);
                        }
                        throw $oException;
                    }
                } elseif (is_array($aResult) && isset($aResult['details'])) {
                    $bPrevState = \Aurora\System\Api::skipCheckUserRole(true);
                    \Aurora\System\Api::skipCheckUserRole($bPrevState);
                    throw new \Aurora\System\Exceptions\ApiException(0, null, trim(str_replace("<br>", "", $aResult['details'])));
                }
            }
            \Aurora\System\Api::skipCheckUserRole($bPrevState);
        }
        return true; // break subscriptions to prevent account creation in other modules
    }
}
