<?php 
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
* LimeSurvey
* Copyright (C) 2007-2011 The LimeSurvey Project Team / Carsten Schmitz
* All rights reserved.
* License: GNU/GPL License v2 or later, see LICENSE.php
* LimeSurvey is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*
*  
*/

/**
* Update Controller
*
* @package        LimeSurvey
* @subpackage    Backend
*  
* This controller performs updates, it is highly ajax oriented
* Methods are only called from JavaScript controller (wich is called from the global_setting view). comfortupdater.js is the first registred script.  
*
*
*   
* Public methods are written in a chronological way :
*    - First, when the user click on the "check for updates" button, the plugin buildComfortButtons.js call for getstablebutton() or getbothbuttons() method and inject the HTML inside the li#udapteButtonsContainer in the _checkButtons view
*   - Then, when the user click on one of those buttons, the comfortUpdateNextStep.js plugin will call for the getWelcome() method and inject the HTML inside div#updaterContainer in the _right_container view (all steps will be then injected here)
*    - Then, when the user click on the continue button, the comfortUpdateNextStep.js plugin will call for the step1() method and inject the  the HTML inside div#updaterContainer in the _right_container view 
*   - etc. etc.
* 
*
* 
*  Some steps must be shown out of the chronological process : getNewKey and submitKey. They are at the end of the controller's interface.
*  Some steps must be "checked again" after the user fixed some errors (such as file permissions). 
*  Those steps are/can be diplayed by the plugin displayComfortStep.js. They are called from buttons like :
* 
*  <a class="button" href="<?php Yii::app()->createUrl("admin/globalsettings", array("update"=>'methodToCall', 'neededVariable'=>$value));?>">
*    <span class="ui-button-text">button text</span>
*  </a>
* 
* so they will call an url such as : globalsettings?update=methodToCall&neededVariable=value. 
* So the globalsetting controller will render the view as usual, but : the _ajaxVariables view will parse those url datas to some hidden field. 
* The comfortupdater.js check the value of the hidden field update, and if the update's one contain a step, it call displayComfortStep.js wich will display the right step instead of the "check update" buttons.
*
* Most steps are retrieving datas from the comfort update server thanks to the model UpdateForm's methods.
* The server return an answer object, with a property "result" to tell if the process was succesfull or if it failed. This object contains in general all the necessary datas for the views.  
* 
*  
* Handling errors :
* They are different types of possible errors :
* - Warning message (like : modified files, etc.) : they don't stop the process, they are parsed to the step view, and the view manage how to display them. They can be generated from the ComfortUpdater server ($answer_from_server->result == TRUE ; and something like $answer_from_server->error == message or anything else that the step view manage ), or in the LimeSurvey update controller/model 
* - Error while processing a request on the server part : should never happen, but if something goes wrong in the server side (like generating an object from model), the server returns an error object ($answer_from_server->result == FALSE ; $answer_from_server->error == message )
*   Those errors stop the process, and are display in _error view. Very usefull to debug. They are parsed directly to $this->_renderError 
* - Error while checking needed datas in the LimeSurvey update controller : the controller always check if it has the needed datas (such as destintion_build, or zip_file), or the state of the key (outdated, etc). For the code to be dryer, the method parse an error string to $this->_renderErrorString($error), wich generate the error object, and then render the error view   
*
*/
class update extends Survey_Common_Action
{
    
    /**
     * This function return the update buttons for stable branch 
     * @return html the button code
     */
    public function getstablebutton()
    {
        echo $this->_getButtons("0");
    }
    
    /**
     * This function return the update buttons for all versions
     * @return html the buttons code
     */
    public function getbothbuttons()
    {
        echo $this->_getButtons("1");        
    }    

    /**
     * This function has a special rendering, because the ComfortUpdate server can choose what it's going to show :
     * the welcome message or the subscribe message or the updater update, etc. 
     * The same system is used for the static views (update key, etc.)
     *
     * @return html the welcome message
     */
    public function getwelcome()
    {
        // We get the update key in the database. If it's empty, getWelcomeMessage will return subscription
        $updateKey = getGlobalSetting("update_key");
        //$updateKey = SettingGlobal::model()->findByPk('update_key')->stg_value;
        $updateModel = new UpdateForm();
        $destinationBuild = $_REQUEST['destinationBuild'];    
           $welcome = (array) $updateModel->getWelcomeMessage($updateKey, $destinationBuild);
           $welcome['destinationBuild'] = $destinationBuild;
        $welcome = (object)$welcome;           
           
           return $this->_renderWelcome($welcome);
    }

    /**
     * returns the "Checking basic requirements" step
     * @return html the welcome message
     */
    public function checkLocalErrors()
    {
        // We use request rather than post, because this step can be called by url by displayComfortStep.js
        if( isset($_REQUEST['destinationBuild']) )
        {
            $destinationBuild = $_REQUEST['destinationBuild'];
            $access_token     = $_REQUEST['access_token'];
            
            $updateModel = new UpdateForm();
            $localChecks = $updateModel->getLocalChecks($destinationBuild);
            $aData['localChecks'] = $localChecks;
            $aData['changelog'] = NULL;
            $aData['destinationBuild'] = $destinationBuild;
            $aData['access_token'] = $access_token;
            
            return $this->controller->renderPartial('update/updater/steps/_check_local_errors', $aData, false, true);
        }
        return $this->_renderErrorString("unkown_destination_build");    
    }

    /**
    * Display change log
    * @return HTML  
    */    
    public function changeLog()
    {
        // We use request rather than post, because this step can be called by url by displayComfortStep.js
        if( isset($_REQUEST['destinationBuild']) )
        {
            
            $destinationBuild = $_REQUEST['destinationBuild'];
            $access_token     = $_REQUEST['access_token'];
            
                // We get the change log from the ComfortUpdater server
                $updateModel = new UpdateForm();
                $changelog = $updateModel->getChangeLog( $destinationBuild );
                
                if( $changelog->result )
                {
                    $aData['errors'] = FALSE;
                    $aData['changelogs'] = $changelog;
                    $aData['html_from_server'] = $changelog->html;
                    $aData['destinationBuild'] = $destinationBuild;
                    $aData['access_token'] = $access_token;
                }
                else 
                {
                    return $this->_renderError($changelog);
                }
                return $this->controller->renderPartial('update/updater/steps/_change_log', $aData, false, true);
        }
        return $this->_renderErrorString("unkown_destination_build");        
    }    
    
    /**
     * diaplay the result of the changed files check 
     * 
     * @return html  HTML 
     */  
    public function fileSystem()
    {
        if( isset($_REQUEST['destinationBuild']))
        {
            $tobuild = $_REQUEST['destinationBuild'];
            $access_token     = $_REQUEST['access_token'];        
            $frombuild = Yii::app()->getConfig("buildnumber");

            $updateModel = new UpdateForm();
            $changedFiles = $updateModel->getChangedFiles($tobuild);
            
            if( $changedFiles->result )
            {
                //TODO : clean that 
                $aData = $updateModel->getFileStatus($changedFiles->files);
                
                $aData['html_from_server'] = ( isset($changedFiles->html) )?$changedFiles->html:'';
                $aData['datasupdateinfo'] = $this->_parseToView($changedFiles->files);
                $aData['destinationBuild']=$tobuild;
                $aData['updateinfo'] = $changedFiles->files;
                $aData['access_token'] = $access_token;
                
                return $this->controller->renderPartial('update/updater/steps/_fileSystem', $aData, false, true);      
            }
            var_dump($changedFiles);
            return $this->_renderError($changedFiles); 
        }
        return $this->_renderErrorString("unkown_destination_build");
    }

    /**
     * backup files
     * @return html  
     */  
    public function backup()
    {
        if(Yii::app()->request->getPost('destinationBuild'))
        {
            $destinationBuild = Yii::app()->request->getPost('destinationBuild');
            $access_token     = $_REQUEST['access_token'];
            
            if(Yii::app()->request->getPost('datasupdateinfo'))
            {
                $updateinfos= unserialize ( base64_decode( ( Yii::app()->request->getPost('datasupdateinfo') )));

                $updateModel = new UpdateForm();
                $backupInfos = $updateModel->backupFiles($updateinfos);

                if( $backupInfos->result )
                {
                    $dbBackupInfos = $updateModel->backupDb($destinationBuild);
                    // If dbBackup fails, it will just provide a warning message : backup manually
                     
                    $aData['dbBackupInfos'] = $dbBackupInfos; 
                    $aData['basefilename']=$backupInfos->basefilename;
                    $aData['tempdir'] = $backupInfos->tempdir;                 
                    $aData['datasupdateinfo'] = $this->_parseToView($updateinfos);
                    $aData['destinationBuild'] = $destinationBuild;
                    $aData['access_token'] = $access_token;
                    return $this->controller->renderPartial('update/updater/steps/_backup', $aData, false, true);
                    
                }
                else 
                {
                    $error = $backup->error;
                }
            }        
            else 
            {
                $error = "no_updates_infos";
            }
        }
        else 
        {
            $error = "unkown_destination_build";
        }
        return $this->_renderErrorString($error);
    }

    /**
     * Display step4
     * @return html 
     */  
    function step4()
    {
        if( Yii::app()->request->getPost('destinationBuild') )
        {
            $destinationBuild = Yii::app()->request->getPost('destinationBuild');
            $access_token     = $_REQUEST['access_token'];
            
            if( Yii::app()->request->getPost('datasupdateinfo') )
            {
                $updateinfos = unserialize ( base64_decode( ( Yii::app()->request->getPost('datasupdateinfo') )));

                // this is the last step - Download the zip file, unpack it and replace files accordingly
                $updateModel = new UpdateForm();    
                $file = $updateModel->downloadUpdateFile($access_token, $destinationBuild);
                
                if( $file->result )
                {
                    $unzip = $updateModel->unzipUpdateFile();
                    if( $unzip->result )
                    {
                        $remove = $updateModel->removeDeletedFiles($updateinfos);
                        if( $remove->result )
                        {
                            // Should never bug (version.php is checked before))
                            $updateModel->updateVersion($destinationBuild);
                            $updateModel->destroyGlobalSettings();
                            // TODO : aData should contains information about each step
                            return $this->controller->renderPartial('update/updater/steps/_final', array(), false, true);    
                        }
                        else 
                        {
                            $error = $remove->error;
                        }
                    }
                    else 
                    {
                        $error = $unzip->error;                        
                    }
                }
                else 
                {
                    $error = $file->error;    
                }
            }        
            else 
            {
                $error = "no_updates_infos";
            }
        }
        else 
        {
            $error = "unkown_destination_build";
        }
        return $this->_renderErrorString($error);                
    }

    /**
     * This function update the updater 
     * It is called from the view _updater_update.
     * The view _updater_update is called by the ComfortUpdater server during the getWelcome step if the updater version is not the minimal required one.  
     * @return html the welcome message
     */
    public function updateUpdater()
    {
        if( Yii::app()->request->getPost('destinationBuild') )
        {
            $destinationBuild = Yii::app()->request->getPost('destinationBuild');        
            $updateModel = new UpdateForm();
            
            $localChecks = $updateModel->getLocalChecksForUpdater();
            
            if( $localChecks->result )
            {
                $file = $updateModel->downloadUpdateUpdaterFile($destinationBuild);
    
                if( $file->result )
                {
                    $unzip = $updateModel->unzipUpdateUpdaterFile();
                    if( $unzip->result )
                    {
                        return $this->controller->renderPartial('update/updater/steps/_updater_updated', array('destinationBuild'=>$destinationBuild), false, true);    
                    }
                    else 
                    {
                        $error = $unzip->error;                        
                    }
                }
                else 
                {
                    $error = $file->error;    
                }
            }
            else 
            {
                return $this->controller->renderPartial('update/updater/welcome/_error_files_update_updater', array('localChecks'=>$localChecks), false, true);
            }
            
        }
        return $this->_renderErrorString($error);
    }

    /**
     * This return the subscribe message 
     * @return html the welcome message
     */
    public function getnewkey()
    {
        // We try to get the update key in the database. If it's empty, getWelcomeMessage will return subscription
        $updateKey = NULL;
        $updateModel = new UpdateForm();
        $destinationBuild = $_REQUEST['destinationBuild'];    
           $welcome = $updateModel->getWelcomeMessage($updateKey, $destinationBuild); //$updateKey        
           echo $this->_renderWelcome($welcome);
    }    

    /**
     * This function create or update the LS update key
     * @return html 
     */
    public function submitkey()
    {
        if( Yii::app()->request->getPost('keyid') ) 
        {
            // We trim it, just in case user added a space... 
            $submittedUpdateKey = trim(Yii::app()->request->getPost('keyid'));    
             
            $updateModel = new UpdateForm();
            $check = $updateModel->checkUpdateKeyonServer($submittedUpdateKey);
            if( $check->result )
            {
                // If the key is validated by server, we update the local database with this key
                $updateKey = $updateModel->setUpdateKey($submittedUpdateKey);
                $check = new stdClass();
                $check->result = TRUE;
                $check->view = "key_updated";
            }
            // then, we render the what returned the server (views and key infos or error )
            echo $this->_renderWelcome($check);
        }
        else 
        {
            return $this->_renderErrorString("key_null");
        }
    }



    /**
    * Update database
    */
    function db($continue = null)
    {
        Yii::app()->loadHelper("update/update");
        if(isset($continue) && $continue=="yes")
        {
            $aViewUrls['output'] = CheckForDBUpgrades($continue);
            updateCheck();
            $aData['display']['header'] = false;
        }
        else
        {
            $aData['display']['header'] = true;
            $aViewUrls['output'] = CheckForDBUpgrades();
        }

        $aData['updatedbaction'] = true;

        $this->_renderWrappedTemplate('update', $aViewUrls, $aData);
    }


    /**
     * this function render the update buttons 
     * @param object $serverAnswer the update server answer (getInfo)  
     */
    private function _getButtons($crosscheck)
    {
        $updateModel = new UpdateForm();
        $serverAnswer = $updateModel->getUpdateInfo($crosscheck);
        
        if( $serverAnswer->result )
        {
            unset($serverAnswer->result);
            return $this->controller->renderPartial('//admin/update/check_updates/update_buttons/_updatesavailable', array('updateInfos' => $serverAnswer));
        }
        // Error : we build the error title and messages 
        return $this->controller->renderPartial('//admin/update/check_updates/update_buttons/_updatesavailable_error', array('serverAnswer' => $serverAnswer));        
    } 

    /**
     * This method render the welcome/subscribe/key_updated message
     * @param obj $serverAnswer the answer return by the server
     */
    private function _renderWelcome($serverAnswer)
    {
        if( $serverAnswer->result )
        {
            // Available views (in /admin/update/welcome/ )
            $views = array('welcome', 'subscribe', 'key_updated', 'updater_update');
            if( in_array($serverAnswer->view, $views) ) 
            {
                return $this->controller->renderPartial('//admin/update/updater/welcome/_'.$serverAnswer->view, array('serverAnswer' => $serverAnswer),  false, true);
            }    
            else 
            {
                $serverAnswer->result = FALSE;
                $serverAnswer->error = "unknown_view";
            }
        }
        echo $this->_renderError($serverAnswer);
        
    }


    /**
     * This method renders the error view
     * @param object $errorObject
     * @return html
     */
    private function _renderError($errorObject)
    {
        echo $this->controller->renderPartial('//admin/update/updater/_error', array('errorObject' => $errorObject), false, true); 
    }

    /**
     * This method convert a string to an error object, and then render the error view 
     * @param string $error the error message 
     * @return html
     */     
    private function _renderErrorString($error)
    {
            $errorObject = new stdClass();
            $errorObject->result = FALSE;
            $errorObject->error = $error;
            return $this->_renderError($errorObject);        
    }

    /**
     * This function convert the huge updateinfos array to a base64 string, so it can be parsed to the view to be inserted in an hidden input element.
     * 
     * @param array $udpateinfos the udpadte infos array returned by the update server
     * @return $string
     */
    private function _parseToView($updateinfos)
    {
        $data=serialize($updateinfos);
        return base64_encode($data);
    }            

}