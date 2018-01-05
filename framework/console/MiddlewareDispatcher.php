<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\console;

use Yii;
use yii\base\Component;
use yii\base\MiddlewareDispatcherInterface;

/**
 * MiddlewareDispatcher
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.1.0
 */
class MiddlewareDispatcher extends Component implements MiddlewareDispatcherInterface
{
    /**
     * {@inheritdoc}
     * @return Response response instance.
     */
    public function dispatch($request, array $middleware, $handler)
    {
        if (empty($middleware)) {
            return call_user_func($handler, $request);
        }

        /* @var $middlewareInstance \yii\console\MiddlewareInterface */
        $middlewareInstance = array_shift($middleware);
        if (!is_object($middlewareInstance) || $middlewareInstance instanceof \Closure) {
            $middlewareInstance = Yii::createObject($middlewareInstance);
        }

        $newHandler = function ($request) use ($middleware, $handler) {
            return $this->dispatch($request, $middleware, $handler);
        };

        return $middlewareInstance->process($request, $newHandler);
    }
}