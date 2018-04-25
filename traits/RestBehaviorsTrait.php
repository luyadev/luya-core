<?php

namespace luya\traits;

use Yii;
use yii\web\Response;
use yii\filters\auth\CompositeAuth;
use yii\filters\auth\QueryParamAuth;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\ContentNegotiator;
use yii\filters\Cors;
use yii\base\Model;
use yii\base\InvalidParamException;
use luya\rest\UserBehaviorInterface;

/**
 * Rest Behaviors Trait.
 *
 * This class overrides the default behaviors method of {{yii\rest\Controller}} controllers.
 *
 * The following changes are differ to the base implementation:
 *
 * + If {{luya\rest\UserBehaviorInterface}} is **not** implemented, the `authenticator` behavior ({{yii\filters\auth\CompositeAuth}}) is removed.
 * + If {{luya\rest\UserBehaviorInterface}} **is** implemented, the `authenticator` behavior ({{yii\filters\auth\CompositeAuth}}) is enabled.
 * + If {{luya\rest\UserBehaviorInterface}} **is** implemented, the `contentNegotiator` behavior ({{yii\filters\ContentNegotiator}}) is enabled.
 * + The `rateLimiter` behavior filter is **removed** by default.
 *
 * Read the {{luya\rest\UserBehaviorInterface}} about the configuration ability to protect the controller.
 *
 * @author Basil Suter <basil@nadar.io>
 * @since 1.0.0
 */
trait RestBehaviorsTrait
{
    /**
     * @var boolean Whether CORS is enabled or not.
     */
    public $enableCors = false;
    
    /**
     * Whether the rest controller is protected or not.
     *
     * @return boolean|\yii\web\User
     */
    private function getUserAuthClass()
    {
        if ($this instanceof UserBehaviorInterface) {
            $class = $this->userAuthClass();
            
            if (!$class) { // return false;
                return false;
            }
            
            if (!is_object($class)) {
                return Yii::createObject($class);
            }
    
            return $class;
        }
        
        return false;
    }

    /**
     * Override the default {{yii\rest\Controller::behaviors()}} method.
     * The following changes are differ to the base implementation:
     *
     * + If {{luya\rest\UserBehaviorInterface}} is **not** implemented, the `authenticator` behavior ({{yii\filters\auth\CompositeAuth}}) is removed.
     * + If {{luya\rest\UserBehaviorInterface}} **is** implemented, the `authenticator` behavior ({{yii\filters\auth\CompositeAuth}}) is enabled.
     * + If {{luya\rest\UserBehaviorInterface}} **is** implemented, the `contentNegotiator` behavior ({{yii\filters\ContentNegotiator}}) is enabled.
     * + The `rateLimiter` behavior filter is **removed** by default.
     *
     * @return array Returns an array with registered behavior filters based on the implementation type.
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        if (!$this->getUserAuthClass()) {
            unset($behaviors['authenticator']);
        } else {
            // change to admin user auth class
            $behaviors['authenticator'] = [
                'class' => CompositeAuth::className(),
                'user' => $this->getUserAuthClass(),
                'authMethods' => [
                    QueryParamAuth::className(),
                    HttpBearerAuth::className(),
                ],
            ];
            
            if ($this->enableCors) {
                $behaviors['authenticator']['except'] = ['options'];
            }
        }
        
        if ($this->enableCors) {
            $behaviors['cors'] = Cors::class;
        }

        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::className(),
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
                'application/xml' => Response::FORMAT_XML,
            ],
        ];
        
        // by default rate limiter behavior is removed as it requires a database
        // user given from the admin module.
        if (isset($behaviors['rateLimiter'])) {
            unset($behaviors['rateLimiter']);
        }

        return $behaviors;
    }
    
    /**
     * Send Model errors with correct headers.
     *
     * Helper method to correctly send model errors with the correct response headers.
     *
     * Example return value:
     *
     * ```php
     * Array
     * (
     *     [0] => Array
     *         (
     *             [field] => firstname
     *             [message] => Firstname cannot be blank.
     *         )
     *     [1] => Array
     *         (
     *             [field] => email
     *             [message] => Email cannot be blank.
     *         )
     * )
     * ```
     *
     * @param \yii\base\Model $model The model to find the first error.
     * @throws \yii\base\InvalidParamException
     * @return array If the model has errors InvalidParamException will be thrown, otherwise an array with message and field key.
     */
    public function sendModelError(Model $model)
    {
        if (!$model->hasErrors()) {
            throw new InvalidParamException('The model as thrown an uknown Error.');
        }
        
        Yii::$app->response->setStatusCode(422, 'Data Validation Failed.');
        $result = [];
        foreach ($model->getFirstErrors() as $name => $message) {
            $result[] = [
                'field' => $name,
                'message' => $message,
            ];
        }
        
        return $result;
    }
    
    /**
     * Send Array validation error.
     *
     * Example input:
     *
     * ```php
     * return $this->sendArrayError(['firstname' => 'Firstname cannot be blank']);
     * ```
     *
     * Example return value:
     *
     * ```php
     * Array
     * (
     *     [0] => Array
     *         (
     *             [field] => firstname
     *             [message] => Firstname cannot be blank.
     *         )
     * )
     * ```
     * @param array $errors Provide an array with messages. Where key is the field and value the message.
     * @return array Returns an array with field and message keys for each item.
     * @since 1.0.3
     */
    public function sendArrayError(array $errors)
    {
        Yii::$app->response->setStatusCode(422, 'Data Validation Failed.');
        $result = [];
        foreach ($errors as $key => $value) {
            $messages = (array) $value;
            
            foreach ($messages as $msg) {
                $result[] = ['field' => $key, 'message' => $msg];
            }
        }
        
        return $result;
    }
}
