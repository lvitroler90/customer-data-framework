<?php

namespace CustomerManagementFramework;

use CustomerManagementFramework\ActionTrigger\Event\SingleCustomerEventInterface;
use CustomerManagementFramework\Controller\Plugin\UrlActivityTracker;
use CustomerManagementFramework\Model\ActivityInterface;
use CustomerManagementFramework\Model\CustomerInterface;
use CustomerManagementFramework\Model\CustomerSegmentInterface;
use Pimcore\API\Plugin as PluginLib;
use Pimcore\Model\Object\ActivityDefinition;

class Plugin extends PluginLib\AbstractPlugin implements PluginLib\PluginInterface
{

    public function init()
    {

        \Pimcore::getEventManager()->attach("system.di.init", function (\Zend_EventManager_Event $e) {
            $builder = $e->getTarget();


            $customFile = PIMCORE_PLUGINS_PATH . '/CustomerManagementFramework/config/di.php';
            if (file_exists($customFile)) {
                $builder->addDefinitions($customFile);
            }

            $customFile = \Pimcore\Config::locateConfigFile("plugins/CustomerManagementFramework/di.php");
            if (file_exists($customFile)) {
                $builder->addDefinitions($customFile);
            }

        });

        parent::init();

        \Pimcore::getEventManager()->attach(["object.preAdd"], function (\Zend_EventManager_Event $e) {
            $object = $e->getTarget();

            if($object instanceof ActivityDefinition) {
                $object->setCode(uniqid());
            }
        });

        \Pimcore::getEventManager()->attach(["object.preUpdate"], function (\Zend_EventManager_Event $e) {
            $object = $e->getTarget();

            if($object instanceof CustomerInterface) {
                Factory::getInstance()->getCustomerSaveManager()->preUpdate($object);
            }

            if($object instanceof CustomerSegmentInterface) {
                Factory::getInstance()->getSegmentManager()->preSegmentUpdate($object);
            }
        });

        \Pimcore::getEventManager()->attach(["object.postUpdate"], function (\Zend_EventManager_Event $e) {
            $object = $e->getTarget();

            if($object instanceof ActivityInterface) {
                $trackIt = true;
                if(!$object->cmfUpdateOnSave()) {
                    if(Factory::getInstance()->getActivityStore()->getEntryForActivity($object)) {
                        $trackIt = false;
                    }
                }

                if($trackIt) {
                    Factory::getInstance()->getActivityManager()->trackActivity($object);
                }

            }

            if($object instanceof CustomerInterface) {
                Factory::getInstance()->getCustomerSaveManager()->postUpdate($object);
            }
        });

        \Pimcore::getEventManager()->attach("object.postDelete", function (\Zend_EventManager_Event $e) {
            $object = $e->getTarget();
            if($object instanceof ActivityInterface) {
                Factory::getInstance()->getActivityManager()->deleteActivity($object);
            }
        });

        \Pimcore::getEventManager()->attach('system.console.init', function(\Zend_EventManager_Event $e) {
            /** @var \Pimcore\Console\Application $application */
            $application = $e->getTarget();

            // add a namespace to autoload commands from
            $application->addAutoloadNamespace('CustomerManagementFramework\\Console', PIMCORE_DOCUMENT_ROOT . '/plugins/CustomerManagementFramework/lib/CustomerManagementFramework/Console');


        });

        \Pimcore::getEventManager()->attach('system.maintenance', function(\Zend_EventManager_Event $e) {
            Factory::getInstance()->getSegmentManager()->executeSegmentBuilderMaintenance();
        });

        \Pimcore::getEventManager()->attach(array_keys(Plugin::getConfig()->Events->toArray()), function(\Zend_EventManager_Event $e) {

            $event = $e->getTarget();

            if($event instanceof SingleCustomerEventInterface) {
                Factory::getInstance()->getActionTriggerEventHandler()->handleSingleCustomerEvent($e, $event);
            }

        });

        $front = \Zend_Controller_Front::getInstance();

        $front->registerPlugin(new UrlActivityTracker(), 1666);

    }

    public function handleDocument($event)
    {
        // do something
        $document = $event->getTarget();
    }

    public static function install()
    {
        $installer = new Installer();

        return $installer->install();
    }

    public static function uninstall()
    {
        // implement your own logic here
        return true;
    }

    public static function isInstalled()
    {
        // implement your own logic here
        return file_exists(PIMCORE_WEBSITE_PATH . '/config/plugins/CustomerManagementFramework/config.php');
    }

    private static function getConfigFile() {
        return \Pimcore\Config::locateConfigFile("plugins/CustomerManagementFramework/config.php");
    }

    /**
     * @param string $language
     * @return null|string
     */
    public static function getTranslationFile($language)
    {
        return sprintf('/CustomerManagementFramework/texts/%s.csv', $language);
    }

    protected static $config = null;
    public static function getConfig() {
        if(is_null(self::$config)) {
            $file = self::getConfigFile();

            if(file_exists($file)) {
                self::$config = new \Zend_Config(include($file));;

            } else {
                throw new \Exception($file . " doesn't exist");
            }
        }


        return self::$config;
    }
}
