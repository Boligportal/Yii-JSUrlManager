<?php
class EJSUrlManager extends CApplicationComponent {
    public function init() {
        parent::init();
        $urlManager = Yii::app()->urlManager;

        $managerVars              = get_object_vars($urlManager);
        $managerVars['urlFormat'] = $urlManager->urlFormat;

        foreach ($managerVars['rules'] as $pattern => $route) {
            //Ignore custom URL classes
            if(is_array($route) && isset($route['class'])) {
                unset($managerVars['rules'][$pattern]);
            }
        }

        $encodedVars = CJSON::encode($managerVars);

        /** @var CAssetManager $assetManager */
        $assetManager = clone Yii::app()->assetManager;
        $assetManager->linkAssets = false;

        // set paths
        $assetsPath = dirname(__FILE__) . '/assets/js';
        $publishUrl  = $assetManager->publish($assetsPath, false, -1);
        $publishPath = $assetManager->getPublishedPath($assetsPath);

        // create hash
        $hash = substr(md5($encodedVars), 0, 10);
        $routesFile = "JsUrlRoutes.{$hash}.js";

        if (!file_exists($publishPath .'/' . $routesFile)) {
            $baseUrl = Yii::app()->getRequest()->getBaseUrl();
            $scriptUrl = Yii::app()->getRequest()->getScriptUrl();
            $hostInfo = Yii::app()->getRequest()->getHostInfo();

            $data = <<< ROUTES_DATA
                var Yii = Yii || {};
                Yii.app = {scriptUrl: '{$scriptUrl}',baseUrl: '{$baseUrl}', hostInfo: '{$hostInfo}'};
                Yii.app.urlManager = new UrlManager({$encodedVars});
                Yii.app.createUrl = function(route, params, ampersand)  {
                return this.urlManager.createUrl(route, params, ampersand);};
ROUTES_DATA;

            // save to dictionary file
            if (!file_put_contents($publishPath . '/' . $routesFile, $data)) {
                Yii::log('Error: Could not write dictionary file', 'trace', 'JsUrlManager');
                return null;
            }
        }

        Yii::app()->getClientScript()->addPackage('JsUrlManager', [
            'baseUrl' => $publishUrl,
            'js' => ['Yii.UrlManager.min.js', $routesFile],
        ]);

        Yii::app()->getClientScript()->registerPackage('JsUrlManager');
    }
}
