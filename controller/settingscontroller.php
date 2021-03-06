<?php
/**
 * ownCloud - agreedisclaimer
 *
 * This file is licensed under the MIT License. See the COPYING file.
 *
 * @author Josef Meile <technosoftgratis@okidoki.com.co>
 * @copyright Josef Meile 2015
 */

namespace OCA\AgreeDisclaimer\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCA\AgreeDisclaimer\AppInfo\Application;
use OCA\AgreeDisclaimer\Utils;
use \OCP\IL10N;

/**
 * Controller to retreive some application settings through ajax
 */
class SettingsController extends Controller {

    /**
     * Creates an instance to the SettingsController 
     */
    public function __construct($AppName, IRequest $request) {
        parent::__construct($AppName, $request);
    }

    /**
     * Gets the specified settings from the application configuration
     *
     * @param string            $settingName    Name of the setting to get
     * @param mixed             $defaultValue   Default value in case that the
     *        setting isn't found
     * @param \OCP\IAppConfig   $appConfig      ownCloud's application
     *        configuration. It is optional and if not passed, it will be
     *        aquired by using the "getAppConfig" method of the Server class
     *
     * @return mixed    The value of the specified setting
     */
    public static function getSetting($settingName, $defaultValue = null,
        $appConfig = null)
    {
        if ($appConfig === null)
            $appConfig = \OC::$server->getAppConfig();

        $appId = Application::APP_ID;
        return $appConfig->getValue($appId, $settingName, $defaultValue);
    }

    /**
     * @PublicPage
     *
     * Gets all the application settings
     *
     * @param   bool    $getFileContents    Whether or not to get the contents
     *          of the text file with the disclaimer text
     * @param   bool    $isAdminForm        Whether or not this method is being
     *          called from the admin form. This is used because the method can
     *          be also called from the login page. Here the differences:
     *          - When called from the admin form, no fall back languages will
     *            be used and all the app settings disgregarding its value will
     *            be return
     *          - When called from the login form, fall back languages will be
     *            used and only the enabled settings will be returned
     *
     * @return  array An array with the application settings with the following
     *          format:
     *          [
     *              'pdfIcon': <url_of_pdf_icon_image>,
     *              '<app_id>UserLang': <language_of_current_user>,
     *              //Defined by the FILE_PREFFIX Application static property
     *              '<app_id>FilePreffix': <naming_preffix_for_files>,
     *              'adminSettings': <array_with_app_settings>
     *          ]
     *          'adminSettings' has the following format:
     *          [
     *              '<appId>DefaultLang     => ['value' => <default_app_lang>],
     *              '<appId>MaxTxtFileSize' => ['value' =>
     *                                          <max_txt_file_size_in_mb>],
     *              '<appId>TxtFile'        => [
     *                  'value'    => <true_or_false>,
     *                  'basePath' => <absolute_path_of_txt_file>,
     *                  'file'     => [
     *                      'exists'  => <does_the_file_exist>,
     *                      'lang'    => <file_language_code>,
     *                      'name'    => <file_name>,
     *                      'path'    => <file_location_in_the_file_system>,
     *                      'url'     => <url_to_file_in_web_browser>,
     *                      'content' => <contents_txt_file>,
     *                      'error'   => <error_message>,
     *                  ],
     *              ],
     *              '<appId>PdfFile'        => [
     *                  'value'    => <true_or_false>,
     *                  'basePath' => <absolute_path_of_pdf_file>,
     *                  'file'     => [
     *                      'exists'  => <does_the_file_exist>,
     *                      'lang'    => <file_language_code>,
     *                      'name'    => <file_name>,
     *                      'path'    => <file_location_in_the_file_system>,
     *                      'url'     => <url_to_file_in_web_browser>,
     *                      'error'   => <error_message>,
     *                  ],
     *              ]
     */
    public function getSettings($getFileContents = true, $isAdminForm = false) {
        $data = array();
        $adminSettings = array();

        $appConfig = \OC::$server->getAppConfig();
        $appId = Application::APP_ID;

        $txtFileProp = $appId . 'TxtFile';
        $adminSettings[$txtFileProp] = array();
        $adminSettings[$txtFileProp]['value'] = self::getSetting($txtFileProp,
            'true', $appConfig);

        $pdfFileProp = $appId . 'PdfFile';
        $adminSettings[$pdfFileProp] = array();
        $adminSettings[$pdfFileProp]['value'] = self::getSetting($pdfFileProp,
            'true', $appConfig);

        $data['pdfIcon'] = \OC::$server->getURLGenerator()->linkTo($appId,
            'pdf' . DIRECTORY_SEPARATOR . 'icon.png');

        $defaultLangProp = $appId . 'DefaultLang';
        $adminSettings[$defaultLangProp] = array();
        $defaultLang = self::getSetting($defaultLangProp, 'en', $appConfig);
        $adminSettings[$defaultLangProp]['value'] = $defaultLang;

        $maxTxtFileSizeProp = $appId . 'MaxTxtFileSize';
        $adminSettings[$maxTxtFileSizeProp] = array();
        $maxTxtFileSize = self::getSetting($maxTxtFileSizeProp, '1',
            $appConfig);
        $adminSettings[$maxTxtFileSizeProp]['value'] = $maxTxtFileSize; 

        if (!$isAdminForm) {
            $userLang = Utils::getUserLang();
            $getFallbackLang = true;
        } else {
            //For the admin form only the default language is
            //interesting since the disclaimer won't be shown
            $userLang = $defaultLang;

            //Here we aren't interested in falling back to the main languages
            $getFallbackLang = false;
        }
        $data[$appId . 'UserLang'] = $userLang;
        $txtFileBasePath = Application::getTxtFilesPath();
        $adminSettings[$txtFileProp]['basePath'] = $txtFileBasePath;
        $pdfFileBasePath = Application::getPdfFilesPath();
        $adminSettings[$pdfFileProp]['basePath'] = $pdfFileBasePath;

        $data[$appId . 'FilePreffix'] = Application::FILE_PREFFIX;
        if (($adminSettings[$txtFileProp]['value'] === 'true') 
          || $isAdminForm) {
            $fileInfo = self::getFile($userLang, $defaultLang, $txtFileBasePath,
                'txt', $getFileContents, $maxTxtFileSize, $getFallbackLang);
            $adminSettings[$txtFileProp]['file'] = $fileInfo;
        }

        if (($adminSettings[$pdfFileProp]['value'] === 'true')
          || $isAdminForm) {
            $fileInfo = self::getFile($userLang, $defaultLang, $pdfFileBasePath,
                'pdf', false, 2, $getFallbackLang);

            $adminSettings[$pdfFileProp]['file'] = $fileInfo;
        }

        $data['adminSettings'] = $adminSettings;
        return $data;
    }

    /**
     * Gets the file information of the specified file in the entered language
     *
     * @param   string  $userLang           Language used by the current user
     * @param   string  $defaultLang        Default language defined in the
     *          application settings
     * @param   string  $basePath           Base path for the file in the file
     *          system
     * @param   string  $fileExt            Extension of the file; it can be:
     *          'txt' or 'pdf'
     * @param   bool    $getContent         Whether or not to get the file
     *          contents; only used for the txt files
     * @param   int     $maxFileSize        Maximum size of the file in
     *          megabytes; only used for the txt files
     * @param   bool    $getFallbackLang    Whether or not to get files for fall
     *          back languages in case that the current and the default
     *          languages aren't found
     *
     * @return array    Array with the file information. It has this format:
     *                  [
     *                      'exists'  => <does_the_file_exist>,
     *                      'lang'    => <file_language_code>,
     *                      'name'    => <file_name>,
     *                      'path'    => <file_location_in_the_file_system>,
     *                      'url'     => <url_to_file_in_web_browser>,
     *                      'content' => <contents_txt_file>,
     *                      'error'   => <error_message>,
     *                  ],
     */
    public static function getFile($userLang, $defaultLang, $basePath, $fileExt,
            $getContent = false, $maxFileSize = 2, $getFallbackLang = true) {
        $fileInfo = array();
        $appId = Application::APP_ID;
        $fileName = Application::FILE_PREFFIX . '_' . $userLang . '.' .
            $fileExt;
        $filePath = $basePath . DIRECTORY_SEPARATOR . $fileName;
        $fileInfo['exists'] = file_exists($filePath);
        $fileInfo['lang'] = $userLang;
        $langFallbacks = Utils::getFallbackLang($userLang);
        $errorMsg = '';
        $userLangFile = $filePath;
        if (!$fileInfo['exists']) {
            $errorMsg = \OCP\Util::getL10N($appId)->t('%s doesn\'t exist.',
                $userLangFile . '<br/>') . ' ' .
                \OCP\Util::getL10N($appId)->t('Please contact the webmaster');
            
            $languages = array();
            if ($getFallbackLang) {
                $languages = array_merge($languages, $langFallbacks);
            }
            if ($userLang !== $defaultLang) {
                $languages[] = $defaultLang;
            }
            foreach ($languages as $langCode) {
                $fileName = Application::FILE_PREFFIX . '_' .
                    $langCode . '.' . $fileExt;
                $filePath = $basePath . DIRECTORY_SEPARATOR . $fileName;
                $fileInfo['exists'] = file_exists($filePath);
                if ($fileInfo['exists']) {
                    $fileInfo['lang'] = $langCode;
                    break;
                }
            }
        }

        $fileInfo['path'] = $filePath;
        $fileInfo['url'] = \OC::$server->getURLGenerator()->linkTo($appId,
            $fileExt . DIRECTORY_SEPARATOR . $fileName);
        $fileInfo['name'] = $fileName;

        $fileInfo['error'] = '';
        if ($getContent && $fileInfo['exists']) {
            $maxBytes = $maxFileSize * 1048576;
            $file_contents = file_get_contents($filePath, false, null, 0,
                $maxBytes);
            if ($file_contents === false) {
                //You have to use === otherwise the empty string will be
                //evaluated to false
                $message = 'Could not read contents from file:\n' . $filePath .
                    '\n\nMake sure that the file exists and that it is ' .
                    'readable by the apache user';

                //This ensures that carriage returns appear in a textarea
                $message = Utils::fixCarriageReturns($message); 
                \OCP\Util::writeLog($appId, $message, \OCP\Util::FATAL);
                $file_contents = '';
                $fileInfo['error'] = $message;
            }
            $fileInfo['content'] = $file_contents;
        } elseif (!$fileInfo['exists']) {
            if ($userLang !== $defaultLang) {
                $errorMsg = \OCP\Util::getL10N($appId)->t('Neither the file:' .
                    ' %s nor: %s exist',
                    ['<br/>'. $userLangFile. '<br/><br/>',
                     '<br/>' . $filePath . '<br/><br/>']) . '. ' .
                    \OCP\Util::getL10N($appId)->t('Please contact the ' . 
                    'webmaster');
            }
            //This ensures that carriage returns appear in a textarea
            $errorMsg = Utils::fixCarriageReturns($errorMsg); 
            $fileInfo['error'] = $errorMsg; 
        }
        return $fileInfo;
    }

    /**
     * Get the file information for the txt and pdf files
     *
     * @param   bool    $isAdminForm    Whether or not is called from the admin
     *          form. This is used because the method can be also called from
     *          the login page. Here the differences:
     *          - When called from the admin form, no fall back languages will
     *            be used 
     *          - When called from the login form, fall back languages will be
     *            used 
     * @param   string  $defaultLang    Default language for which the file will
     *          be recovered in case that it doesn't exist for the current
     *          language. In case that it is null, then the default language of
     *          the application will be used.
     *
     * @return array   Array with the file information for the txt and pdf
     *         files. It has this format:
     *          [
     *              '<appId>TxtFile'        => [
     *                  'value'    => <true_or_false>,
     *                  'basePath' => <absolute_path_of_txt_file>,
     *                  'file'     => [
     *                      'exists'  => <does_the_file_exist>,
     *                      'lang'    => <file_language_code>,
     *                      'name'    => <file_name>,
     *                      'path'    => <file_location_in_the_file_system>,
     *                      'url'     => <url_to_file_in_web_browser>,
     *                      'content' => <contents_txt_file>,
     *                      'error'   => <error_message>,
     *                  ],
     *              ],
     *              '<appId>PdfFile'        => [
     *                  'value'    => <true_or_false>,
     *                  'basePath' => <absolute_path_of_pdf_file>,
     *                  'file'     => [
     *                      'exists'  => <does_the_file_exist>,
     *                      'lang'    => <file_language_code>,
     *                      'name'    => <file_name>,
     *                      'path'    => <file_location_in_the_file_system>,
     *                      'url'     => <url_to_file_in_web_browser>,
     *                      'error'   => <error_message>,
     *                  ],
     *              ]
     *          ]
     */
    function getFiles($isAdminForm = false, $defaultLang = null) {
        $data = array();
        $appId = Application::APP_ID;
        $appConfig = \OC::$server->getAppConfig();

        if ($defaultLang === null) {
            $defaultLangProp = $appId . 'DefaultLang';
                    $defaultLang = self::getSetting($defaultLangProp, 'en',
                $appConfig);
        }

        if (!$isAdminForm) {
            $userLang = Utils::getUserLang();
            $getFallbackLang = true;
        } else {
            $userLang = $defaultLang;
            $getFallbackLang = false;
        }

        $maxTxtFileSizeProp = $appId . 'MaxTxtFileSize';
        $maxTxtFileSize = self::getSetting($maxTxtFileSizeProp, '1',
                $appConfig);
        $txtFileBasePath = Application::getTxtFilesPath();
        $pdfFileBasePath = Application::getPdfFilesPath(); 

        $fileInfo = self::getFile($userLang, $defaultLang, $txtFileBasePath,
            'txt', true, $maxTxtFileSize, $getFallbackLang);
        $data['txtFile'] = $fileInfo;

        $fileInfo = self::getFile($userLang, $defaultLang, $pdfFileBasePath,
            'pdf', false, 2, $getFallbackLang);
        $data['pdfFile'] = $fileInfo;
        return $data;
    }
}
