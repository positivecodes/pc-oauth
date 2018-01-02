<?php

namespace pc\yii2\oauth2server;

use ReflectionClass;
use Yii;
use yii\base\InvalidConfigException;
use yii\i18n\PhpMessageSource;

/**
 * For example,
 * 
 * ```php
 * 'oauth2' => [
 *     'class' => 'pc\yii2\oauth2server\Module',
 *     'tokenParamName' => 'accessToken',
 *     'tokenAccessLifetime' => 3600 * 24,
 *     'storageMap' => [
 *         'user_credentials' => 'common\models\User',
 *     ],
 *     'grantTypes' => [
 *         'user_credentials' => [
 *             'class' => 'OAuth2\GrantType\UserCredentials',
 *         ],
 *         'refresh_token' => [
 *             'class' => 'OAuth2\GrantType\RefreshToken',
 *             'always_issue_new_refresh_token' => true
 *         ]
 *     ]
 * ]
 * ```
 */
class Module extends \yii\base\Module
{
    /**
     * @var array Model's map
     */
    public $modelMap = [];
    
    /**
     * @var array Storage's map
     */
    public $storageMap = [];
    
    /**
     * @var array GrantTypes collection
     */
    public $grantTypes = [];
    
    /**
     * @var string name of access token parameter
     */
    public $tokenParamName;
    
    /**
     * @var int max access lifetime
     */
    public $tokenAccessLifetime;

    /**
     * @inheritdoc
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        parent::init();
        $this->registerTranslations();
    }

    /**
     * Gets Oauth2 Server
     *
     * @return object|Server
     * @throws \ReflectionException
     * @throws \yii\di\NotInstantiableException
     * @throws InvalidConfigException
     */
    public function getServer()
    {
        if(!$this->has('server')) {
            $storages = [];
            foreach($this->storageMap as $key => $name) {
                $storages[$key] = Yii::$container->get($name);
            }

            $grantTypes = [];
            foreach($this->grantTypes as $name => $options) {
                if(!isset($storages[$name]) || empty($options['class'])) {
                    throw new InvalidConfigException('Invalid grant types configuration.');
                }

                $class = $options['class'];
                unset($options['class']);

                $reflection = new ReflectionClass($class);
                $config = array_merge([0 => $storages[$name]], [$options]);

                $instance = $reflection->newInstanceArgs($config);
                $grantTypes[$name] = $instance;
            }
            
            $server = Yii::$container->get(Server::className(), [
                $this,
                $storages,
                [
                    'token_param_name' => $this->tokenParamName,
                    'access_lifetime' => $this->tokenAccessLifetime,
                    /** add more ... */
                ],
                $grantTypes
            ]);

            $this->set('server', $server);
        }
        return $this->get('server');
    }

    /**
     * @return null|object
     * @throws InvalidConfigException
     */
    public function getRequest()
    {
        if (!$this->has('request') || !$this->get('request') instanceof Request) {
            $this->set('request', Request::createFromGlobals());
        }
        return $this->get('request');
    }

    /**
     * @return null|object
     * @throws InvalidConfigException
     */
    public function getResponse()
    {
        if (!$this->has('response') || !$this->get('response') instanceof Response) {
            $this->set('response', new Response());
        }
        return $this->get('response');
    }

    /**
     * Register translations for this module
     * @throws \yii\base\InvalidConfigException
     */
    public function registerTranslations()
    {
        if (empty(Yii::$app->get('i18n')->translations['modules/oauth2/*'])) {
            Yii::$app->get('i18n')->translations['modules/oauth2/*'] = [
                'class'    => PhpMessageSource::className(),
                'basePath' => __DIR__ . '/messages',
            ];
        }
    }
    
    /**
     * Translate module message
     * 
     * @param string $category
     * @param string $message
     * @param array $params
     * @param string $language
     * @return string
     */
    public static function t($category, $message, $params = [], $language = null)
    {
        return Yii::t('modules/oauth2/' . $category, $message, $params, $language);
    }
}
