<?php

namespace luya\web;

use yii\helpers\ArrayHelper;
use luya\base\AdminModuleInterface;
use luya\TagParser;
use luya\base\BaseBootstrap;

/**
 * LUYA base bootstrap class which will be called during the bootstraping process.
 *
 * @author Basil Suter <basil@nadar.io>
 * @since 1.0.0
 */
class Bootstrap extends BaseBootstrap
{
    private $_apis = [];

    private $_urlRules = [];

    private $_adminAssets = [];

    private $_adminMenus = [];
    
    private $_jsTranslations = [];
    
    /**
     * Before bootstrap run process.
     *
     * @see \luya\base\BaseBootstrap::beforeRun()
     */
    public function beforeRun($app)
    {
        foreach ($app->tags as $name => $config) {
            TagParser::inject($name, $config);
        }
        
        foreach ($this->getModules() as $id => $module) {
            foreach ($module->urlRules as $key => $rule) {
                if (is_string($key)) {
                    $this->_urlRules[$key] = $rule;
                } else {
                    $this->_urlRules[] = $rule;
                }
            }
            
            foreach ($module->apis as $alias => $class) {
                $this->_apis[$alias] = ['class' => $class, 'module' => $module];
            }
            
            foreach ($module->tags as $name => $config) {
                TagParser::inject($name, $config);
            }
        }
    }

    /**
     * Invokes the bootstraping process.
     *
     * @see \luya\base\BaseBootstrap::run()
     */
    public function run($app)
    {
        if (!$app->request->getIsConsoleRequest()) {
            if ($this->hasModule('admin') && $app->request->isAdmin) {
                // When admin context, change csrf token, this will not terminate the frontend csrf token:
                // @see https://github.com/luyadev/luya/issues/1778
                $app->request->csrfParam = '_csrf_admin';
                
                foreach ($this->getModules() as $id => $module) {
                    if ($module instanceof AdminModuleInterface) {
                        $this->_adminAssets = ArrayHelper::merge($module->getAdminAssets(), $this->_adminAssets);
                        if ($module->getMenu()) {
                            $this->_adminMenus[$module->getMenuID()?$module->getMenuID()?$module->id] = $module->getMenu();
                        }
                        $this->_jsTranslations[$id] = $module->getJsTranslationMessages();
                    }
                }
                
                $app->getModule('admin')->assets = $this->_adminAssets;
                $app->getModule('admin')->controllerMap = $this->_apis;
                $app->getModule('admin')->moduleMenus = $this->_adminMenus;
                $app->getModule('admin')->setJsTranslations($this->_jsTranslations);
            }
        }
        
        $app->getUrlManager()->addRules($this->_urlRules);
    }
}
