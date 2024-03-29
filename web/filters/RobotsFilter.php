<?php

namespace luya\web\filters;

use luya\exceptions\WhitelistedException;
use luya\helpers\ArrayHelper;
use Yii;
use yii\base\ActionFilter;
use yii\base\Controller;
use yii\helpers\VarDumper;

/**
 * Prevent Robots from sending Forms.
 *
 * This is a very basic spam protection method. If someone sends the form faster then in the
 * given {{luya\web\filters\RobotsFilter::$delay}} seconds delay time, an InvalidCallException will be thrown.
 *
 * Usage:
 *
 * ```php
 * public function behaviors()
 * {
 *     return [
 *         'robotsFilter' => RobotsFilter::class
 *     ];
 * }
 * ```
 *
 * In order to configure the capture delay time use:
 *
 * ```php
 * public function behaviors()
 * {
 *     return [
 *         'robotsFilter' => [
 *             'class' => RobotsFilter::class,
 *             'delay' => 0.5,
 *         ]
 *     ];
 * }
 * ```
 *
 * Its also recommend to use {{luya\widgets\SubmitButtonWidget}} when creating forms.
 *
 * @author Basil Suter <basil@nadar.io>
 * @since 1.0.0
 */
class RobotsFilter extends ActionFilter
{
    /**
     * @var float The number of seconds a human would have to fill up the form, before the form is triggered as invalid.
     */
    public $delay = 2.5;

    /**
     * @var string|null A string which identifiers the current robots filter in case you have multiple controllers on the same page with robot filters enabled.
     * @since 1.0.17
     */
    public $sessionKey;

    public const ROBOTS_FILTER_SESSION_IDENTIFIER = '__robotsFilterRenderTime';

    /**
     * @return integer Returns the latest render timestamp.
     */
    protected function getRenderTime()
    {
        $value = Yii::$app->session->get(self::ROBOTS_FILTER_SESSION_IDENTIFIER, [$this->getSessionKeyByOwner() => time()]);

        if (isset($value[$this->getSessionKeyByOwner()])) {
            return $value[$this->getSessionKeyByOwner()];
        }

        return time();
    }

    /**
     * Render Time Setter.
     *
     * @param integer $time Set the last action timestamp.
     */
    protected function setRenderTime($time)
    {
        $merge = Yii::$app->session->get(self::ROBOTS_FILTER_SESSION_IDENTIFIER, []);
        Yii::$app->session->set(self::ROBOTS_FILTER_SESSION_IDENTIFIER, array_merge([$this->getSessionKeyByOwner() => $time], $merge));
    }

    /**
     * Get a specific key for the current robots filter session array.
     *
     * This ensures that when multiple forms are on the same page, only the robot check is handeld for the given module name.
     *
     * @return string
     * @since 1.0.17
     */
    protected function getSessionKeyByOwner()
    {
        if ($this->sessionKey) {
            return $this->sessionKey;
        }

        if ($this->owner instanceof Controller) {
            return $this->owner->module->id;
        }

        return 'generic';
    }

    /**
     * Return the elapsed process time to fill in the form.
     *
     * @return number The elapsed time in seconds.
     */
    protected function getElapsedProcessTime()
    {
        return (int) (time() - $this->getRenderTime());
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        if (Yii::$app->request->isPost) {
            if ($this->getElapsedProcessTime() < $this->delay) {
                throw new WhitelistedException("Robots Filter has detected an invalid Request: " . VarDumper::export(ArrayHelper::coverSensitiveValues(Yii::$app->request->post())));
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function afterAction($action, $result)
    {
        $this->setRenderTime(time());

        return $result;
    }
}
