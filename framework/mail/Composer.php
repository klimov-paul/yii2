<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mail;

use Yii;
use yii\base\BaseObject;
use yii\base\InvalidConfigException;
use yii\web\View;

/**
 * Composer composes the mail messages via view rendering.
 *
 * @property \yii\base\View $view View instance. Note that the type of this property differs in getter and setter. See
 * [[getView()]] and [[setView()]] for details.
 * @property string $viewPath The directory that contains the view files for composing mail messages Defaults
 * to '@app/mail'.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.1
 */
class Composer extends BaseObject
{
    /**
     * @var string|bool HTML layout view name.
     * See [[Template::$htmlLayout]] for detailed documentation.
     */
    public $htmlLayout = 'layouts/html';
    /**
     * @var string|bool text layout view name.
     * See [[Template::$textLayout]] for detailed documentation.
     */
    public $textLayout = 'layouts/text';
    /**
     * @var array the configuration that should be applied to any newly created message template.
     */
    public $templateConfig = [];

    /**
     * @var \yii\base\View|array view instance or its array configuration.
     */
    private $_view = [];
    /**
     * @var string the directory containing view files for composing mail messages.
     */
    private $_viewPath;


    /**
     * @return string the directory that contains the view files for composing mail messages
     * Defaults to '@app/mail'.
     */
    public function getViewPath()
    {
        if ($this->_viewPath === null) {
            $this->setViewPath('@app/mail');
        }
        return $this->_viewPath;
    }

    /**
     * @param string $path the directory that contains the view files for composing mail messages
     * This can be specified as an absolute path or a path alias.
     */
    public function setViewPath($path)
    {
        $this->_viewPath = Yii::getAlias($path);
    }

    /**
     * @param array|\yii\base\View $view view instance or its array configuration that will be used to
     * render message bodies.
     * @throws InvalidConfigException on invalid argument.
     */
    public function setView($view)
    {
        if (!is_array($view) && !is_object($view)) {
            throw new InvalidConfigException('"' . get_class($this) . '::view" should be either object or configuration array, "' . gettype($view) . '" given.');
        }
        $this->_view = $view;
    }

    /**
     * @return \yii\base\View view instance.
     */
    public function getView()
    {
        if (!is_object($this->_view)) {
            $this->_view = $this->createView($this->_view);
        }

        return $this->_view;
    }

    /**
     * Creates view instance from given configuration.
     * @param array $config view configuration.
     * @return \yii\base\View view instance.
     */
    protected function createView(array $config)
    {
        if (!array_key_exists('class', $config)) {
            $config['class'] = View::class;
        }

        return Yii::createObject($config);
    }

    /**
     * Creates new message view template.
     * The newly created instance will be initialized with the configuration specified by [[templateConfig]].
     * @param string|array $viewName view name for the template.
     * @return Template message template instance.
     * @throws InvalidConfigException if the [[templateConfig]] is invalid.
     */
    protected function createTemplate($viewName)
    {
        $config = $this->templateConfig;
        if (!array_key_exists('class', $config)) {
            $config['class'] = Template::class;
        }
        if (!array_key_exists('view', $config)) {
            $config['view'] = $this->getView();
        }

        $config['viewPath'] = $this->getViewPath();
        $config['htmlLayout'] = $this->htmlLayout;
        $config['textLayout'] = $this->textLayout;
        $config['viewName'] = $viewName;

        return Yii::createObject($config);
    }

    /**
     * @param MessageInterface $message the message to be composed.
     * @param string|array $view the view to be used for rendering the message body. This can be:
     *
     * - a string, which represents the view name or path alias for rendering the HTML body of the email.
     *   In this case, the text body will be generated by applying `strip_tags()` to the HTML body.
     * - an array with 'html' and/or 'text' elements. The 'html' element refers to the view name or path alias
     *   for rendering the HTML body, while 'text' element is for rendering the text body. For example,
     *   `['html' => 'contact-html', 'text' => 'contact-text']`.
     *
     * @param array $params the parameters (name-value pairs) that will be extracted and made available in the view file.
     */
    public function compose($message, $view, array $params = [])
    {
        $this->createTemplate($view)->compose($message, $params);
    }
}