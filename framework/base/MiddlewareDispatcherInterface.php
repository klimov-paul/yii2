<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\base;

/**
 * MiddlewareDispatcherInterface defines the application component, which should handle middleware processing.
 * This component handles multiple middleware composed into a stack.
 * 
 * @see MiddlewareDispatcher
 * @see \yii\http\server\MiddlewareDispatcher
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.1.0
 */
interface MiddlewareDispatcherInterface
{
    /**
     * @param object $request application request instance.
     * @param array $middleware middleware stack.
     * @param callable $handler final request handler.
     * @return object response instance.
     */
    public function dispatch($request, array $middleware, $handler);
}