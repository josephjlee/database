<?php

namespace Illuminate\Database\Eloquent\Concerns;

use Illuminate\Contracts\Events\Dispatcher;

trait HasEvents
{
    /**
     * The event map for the model.
     *
     * Allows for object-based events for native Eloquent events.
     *
     * @var array
     */
    protected $events = [];

    /**
     * User exposed observable events.
     *
     * These are extra user-defind events observers may subscribe to.
     *
     * @var array
     */
    protected $observables = [];

    /**
     * Register an observer with the Model.
     *
     * @param  object|string  $class
     * @param  int  $priority
     * @return void
     */
    public static function observe($class, $priority = 0)
    {
        $instance = new static;

        $className = is_string($class) ? $class : get_class($class);

        // When registering a model observer, we will spin through the possible events
        // and determine if this observer has that method. If it does, we will hook
        // it into the model's event system, making it convenient to watch these.
        foreach ($instance->getObservableEvents() as $event) {
            if (method_exists($class, $event)) {
                static::registerModelEvent($event, $className.'@'.$event, $priority);
            }
        }
    }

    /**
     * Get the observable event names.
     *
     * @return array
     */
    public function getObservableEvents()
    {
        return array_merge(
            [
                'creating', 'created', 'updating', 'updated',
                'deleting', 'deleted', 'saving', 'saved',
                'restoring', 'restored',
            ],
            $this->observables
        );
    }

    /**
     * Set the observable event names.
     *
     * @param  array  $observables
     * @return $this
     */
    public function setObservableEvents(array $observables)
    {
        $this->observables = $observables;

        return $this;
    }

    /**
     * Add an observable event name.
     *
     * @param  array|mixed  $observables
     * @return void
     */
    public function addObservableEvents($observables)
    {
        $this->observables = array_unique(array_merge(
            $this->observables, is_array($observables) ? $observables : func_get_args()
        ));
    }

    /**
     * Remove an observable event name.
     *
     * @param  array|mixed  $observables
     * @return void
     */
    public function removeObservableEvents($observables)
    {
        $this->observables = array_diff(
            $this->observables, is_array($observables) ? $observables : func_get_args()
        );
    }

    /**
     * Register a model event with the dispatcher.
     *
     * @param  string  $event
     * @param  \Closure|string  $callback
     * @param  int  $priority
     * @return void
     */
    protected static function registerModelEvent($event, $callback, $priority = 0)
    {
        if (isset(static::$dispatcher)) {
            $name = static::class;

            static::$dispatcher->listen("eloquent.{$event}: {$name}", $callback, $priority);
        }
    }

    /**
     * Fire the given event for the model.
     *
     * @param  string  $event
     * @param  bool  $halt
     * @return mixed
     */
    protected function fireModelEvent($event, $halt = true)
    {
        if (! isset(static::$dispatcher)) {
            return true;
        }

        // First, we will get the proper method to call on the event dispatcher, and then we
        // will attempt to fire a custom, object based event for the given event. If that
        // returns a result we can return that result, or we'll call the string events.
        $method = $halt ? 'until' : 'fire';

        $result = $this->fireCustomModelEvent($event, $method);

        return ! is_null($result) ? $result : static::$dispatcher->{$method}(
            "eloquent.{$event}: ".static::class, $this
        );
    }

    /**
     * Fire a custom model event for the given event.
     *
     * @param  string  $event
     * @param  string  $method
     * @return mixed|null
     */
    protected function fireCustomModelEvent($event, $method)
    {
        if (! isset($this->events[$event])) {
            return;
        }

        $result = static::$dispatcher->$method(new $this->events[$event]($this));

        if (! is_null($result)) {
            return $result;
        }
    }

    /**
     * Register a saving model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @param  int  $priority
     * @return void
     */
    public static function saving($callback, $priority = 0)
    {
        static::registerModelEvent('saving', $callback, $priority);
    }

    /**
     * Register a saved model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @param  int  $priority
     * @return void
     */
    public static function saved($callback, $priority = 0)
    {
        static::registerModelEvent('saved', $callback, $priority);
    }

    /**
     * Register an updating model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @param  int  $priority
     * @return void
     */
    public static function updating($callback, $priority = 0)
    {
        static::registerModelEvent('updating', $callback, $priority);
    }

    /**
     * Register an updated model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @param  int  $priority
     * @return void
     */
    public static function updated($callback, $priority = 0)
    {
        static::registerModelEvent('updated', $callback, $priority);
    }

    /**
     * Register a creating model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @param  int  $priority
     * @return void
     */
    public static function creating($callback, $priority = 0)
    {
        static::registerModelEvent('creating', $callback, $priority);
    }

    /**
     * Register a created model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @param  int  $priority
     * @return void
     */
    public static function created($callback, $priority = 0)
    {
        static::registerModelEvent('created', $callback, $priority);
    }

    /**
     * Register a deleting model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @param  int  $priority
     * @return void
     */
    public static function deleting($callback, $priority = 0)
    {
        static::registerModelEvent('deleting', $callback, $priority);
    }

    /**
     * Register a deleted model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @param  int  $priority
     * @return void
     */
    public static function deleted($callback, $priority = 0)
    {
        static::registerModelEvent('deleted', $callback, $priority);
    }

    /**
     * Remove all of the event listeners for the model.
     *
     * @return void
     */
    public static function flushEventListeners()
    {
        if (! isset(static::$dispatcher)) {
            return;
        }

        $instance = new static;

        foreach ($instance->getObservableEvents() as $event) {
            static::$dispatcher->forget("eloquent.{$event}: ".static::class);
        }
    }

    /**
     * Get the event dispatcher instance.
     *
     * @return \Illuminate\Contracts\Events\Dispatcher
     */
    public static function getEventDispatcher()
    {
        return static::$dispatcher;
    }

    /**
     * Set the event dispatcher instance.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $dispatcher
     * @return void
     */
    public static function setEventDispatcher(Dispatcher $dispatcher)
    {
        static::$dispatcher = $dispatcher;
    }

    /**
     * Unset the event dispatcher for models.
     *
     * @return void
     */
    public static function unsetEventDispatcher()
    {
        static::$dispatcher = null;
    }
}
