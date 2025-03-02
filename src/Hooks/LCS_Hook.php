<?php
namespace LCSNG_EXT\Hooks;

use LCSNG_EXT\Hooks\LCS_HookConfig;

final class LCS_Hook extends LCS_HookConfig {

    private $filter = array();
    private $filters = array();
    private $actions = array();
    private $current_filter = array();

    /**
     * Adds a filter callback to a hook.
     *
     * This function is used to add a filter callback function to a hook. It ensures that the $filter property
     * is initialized as an array, then checks if the hook already exists. If not,
     * it creates a new LCS_Hook object for the hook. Finally, it adds the filter callback to the hook.
     *
     * @param string   $hook_name     The name of the hook to which the filter should be added.
     * @param callable $callback      The callback function to be executed when the hook is triggered.
     * @param int      $priority      Optional. The priority of the filter function. Default is 10.
     * @param int      $accepted_args Optional. The number of arguments the callback function accepts. Default is 1.
     * @return bool True on success, false on failure.
     */
    public function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
        // Ensure $this->filter is initialized as an array
        if ( ! isset( $this->filter ) || ! is_array( $this->filter ) ) {
            $this->filter = array();
        }

        // Check if the hook already exists, otherwise create a new LCS_Hook object
        if ( ! isset( $this->filter[ $hook_name ] ) || ! $this->filter[ $hook_name ] instanceof LCS_Hook ) {
            $this->filter[ $hook_name ] = new LCS_Hook();
        }

        // Add the filter
        $this->filter[ $hook_name ]->_add_filter( $hook_name, $callback, $priority, $accepted_args );

        return true;
    }



    /**
     * Applies filters to a hook.
     *
     * This function is used to apply filters to a hook. It first checks if the hook exists in the
     * $this->filter array. If it does, it proceeds to apply the filters using the LCS_Hook object
     * associated with the hook name. If the 'all' hook exists, it runs all associated callbacks before
     * running the callbacks for the specified hook. The function keeps track of the current filter being
     * executed in the $current_filter property.
     *
     * @param string $hook_name The name of the hook to which the filters should be applied.
     * @param mixed  $value     The value to filter.
     * @param mixed  ...$args   Optional. Additional arguments to pass to the filter callbacks.
     * @return mixed The filtered value after all hooked functions are applied to it.
     */
    public function apply_filters( $hook_name, $value, ...$args ) {
        // Ensure $this->filters is initialized as an array
        if ( ! isset( $this->filters ) || ! is_array( $this->filters ) ) {
            $this->filters = array();
        }

        // Increment filter count for the hook
        if ( ! isset( $this->filters[ $hook_name ] ) ) {
            $this->filters[ $hook_name ] = 1;
        } else {
            ++$this->filters[ $hook_name ];
        }

        // Do 'all' actions first
        if ( isset( $this->filter['all'] ) ) {
            $this->current_filter[] = $hook_name;

            // Call all hooks associated with 'all'
            $all_args = func_get_args(); // Get all arguments passed to the function
            $this->call_all_hook( $all_args );
        }

        // If the hook doesn't exist, return the original value
        if ( ! isset( $this->filter[ $hook_name ] ) ) {
            if ( isset( $this->filter['all'] ) ) {
                array_pop( $this->current_filter );
            }

            return $value;
        }

        // If 'all' hook is not present, add the current hook to $this->current_filter
        if ( ! isset( $this->filter['all'] ) ) {
            $this->current_filter[] = $hook_name;
        }

        // Pass the value to LCS_Hook
        array_unshift( $args, $value );

        // Apply filters using LCS_Hook object
        $filtered = $this->filter[ $hook_name ]->_apply_filters( $value, $args );

        // Remove the current hook from $this->current_filter
        array_pop( $this->current_filter );

        return $filtered;
    }


    /**
     * Checks if a filter is registered for a specific hook.
     *
     * @param string $hook_name The name of the filter hook.
     * @param callable|false $callback Optional. The specific callback function to check for. Default false.
     * @return bool|int If $callback is omitted, returns boolean for whether the hook has anything registered.
     *                  When checking a specific function, the priority of that hook is returned, or false if the function is not attached.
     */
    public function has_filter( $hook_name, $callback = false ) {
        if ( ! isset( $this->filter[ $hook_name ] ) ) {
            return false;
        }

        return $this->filter[ $hook_name ]->_has_filter( $hook_name, $callback );
    }


    /**
     * Removes a callback function from a filter hook.
     *
     * @param string $hook_name The name of the filter hook.
     * @param callable $callback The callback function to remove.
     * @param int $priority Optional. The priority of the function. Default is 10.
     * @return bool True if the function was successfully removed, false otherwise.
     */
    public function remove_filter( $hook_name, $callback, $priority = 10 ) {
        $r = false;

        if ( isset( $this->filter[ $hook_name ] ) ) {
            $r = $this->filter[ $hook_name ]->_remove_filter( $hook_name, $callback, $priority );

            // If there are no callbacks left, remove the filter hook.
            if ( ! $this->filter[ $hook_name ]->callbacks ) {
                unset( $this->filter[ $hook_name ] );
            }
        }

        return $r;
    }


    /**
     * Returns the name of the current filter hook being executed.
     *
     * @return string The name of the current filter hook.
     */
    public function current_filter() {
        return end( $this->current_filter );
    }


    /**
     * Determines whether a filter is currently being executed.
     *
     * @param string|null $hook_name Optional. The name of the filter hook to check. Default is null, meaning any filter.
     * @return bool True if the filter is currently being executed, false otherwise.
     */
    public function doing_filter( $hook_name = null ) {
        if ( null === $hook_name ) {
            return ! empty( $this->current_filter );
        }

        return in_array( $hook_name, $this->current_filter, true );
    }


    /**
     * Removes all callback functions from a filter hook.
     *
     * @param string $hook_name The name of the filter hook.
     * @param int|false $priority Optional. The priority number to remove. Default false, meaning all priorities.
     * @return bool True if the callbacks were successfully removed, false otherwise.
     */
    public function remove_all_filters( $hook_name, $priority = false ) {
        if ( isset( $this->filter[ $hook_name ] ) ) {
            $this->filter[ $hook_name ]->_remove_all_filters( $priority );

            // If there are no filters left, remove the filter hook.
            if ( ! $this->filter[ $hook_name ]->_has_filters() ) {
                unset( $this->filter[ $hook_name ] );
            }
        }

        return true;
    }

    /**
     * Adds a callback function to an action hook.
     *
     * This function is a wrapper for add_filter() specifically for actions.
     *
     * @param string   $hook_name     The name of the action hook.
     * @param callable $callback      The callback function to add to the action hook.
     * @param int      $priority      Optional. The priority at which the function should be executed. Default is 10.
     * @param int      $accepted_args Optional. The number of arguments the function accepts. Default is 1.
     * @return true|false True on success, false on failure.
     */
    public function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
        return $this->add_filter( $hook_name, $callback, $priority, $accepted_args );
    }

    /**
     * Executes all functions hooked to the specified action hook.
     *
     * @param string $hook_name The name of the action hook.
     * @param mixed  ...$arg    Optional. Additional arguments passed to the callback functions.
     */
    public function do_action( $hook_name, ...$arg ) {
        // Initialize the count of times this action is fired.
        if ( ! isset( $this->actions[ $hook_name ] ) ) {
            $this->actions[ $hook_name ] = 1;
        } else {
            ++$this->actions[ $hook_name ];
        }

        // Execute 'all' actions first.
        if ( isset( $this->filter['all'] ) ) {
            $this->current_filter[] = $hook_name;
            $all_args = func_get_args(); // Get all function arguments.
            $this->call_all_hook( $all_args ); // Call all 'all' hook callbacks.
        }

        // If no callbacks are registered for the action, return.
        if ( ! isset( $this->filter[ $hook_name ] ) ) {
            if ( isset( $this->filter['all'] ) ) {
                array_pop( $this->current_filter );
            }
            return;
        }

        // Add the current hook to the stack of current filters being processed.
        if ( ! isset( $this->filter['all'] ) ) {
            $this->current_filter[] = $hook_name;
        }

        // Ensure $arg is not empty.
        if ( empty( $arg ) ) {
            $arg[] = '';
        } elseif ( is_array( $arg[0] ) && 1 === count( $arg[0] ) && isset( $arg[0][0] ) && is_object( $arg[0][0] ) ) {
            // Backward compatibility for PHP4-style passing of `array( &$this )` as action argument.
            $arg[0] = $arg[0][0];
        }

        // Execute all registered callbacks for this action.
        $this->filter[ $hook_name ]->_do_action( $arg );

        // Remove the current hook from the stack.
        array_pop( $this->current_filter );
    }


    /**
     * Checks if an action is registered for a specific hook.
     *
     * @param string $hook_name The name of the action hook.
     * @param callable|false $callback Optional. The specific callback function to check for. Default false.
     * @return bool|int If $callback is omitted, returns boolean for whether the hook has anything registered.
     *                  When checking a specific function, the priority of that hook is returned, or false if the function is not attached.
     */
    public function has_action( $hook_name, $callback = false ) {
        return $this->has_filter( $hook_name, $callback );
    }

    /**
     * Removes a callback function from an action hook.
     *
     * @param string $hook_name The name of the action hook.
     * @param callable $callback The callback function to remove.
     * @param int $priority Optional. The priority of the function. Default is 10.
     * @return bool True if the function was successfully removed, false otherwise.
     */
    public function remove_action( $hook_name, $callback, $priority = 10 ) {
        return $this->remove_filter( $hook_name, $callback, $priority );
    }

    /**
     * Returns the name of the current action hook being executed.
     *
     * @return string The name of the current action hook.
     */
    public function current_action() {
        return $this->current_filter();
    }

    /**
     * Determines whether an action is currently being executed.
     *
     * @param string|null $hook_name Optional. The name of the action hook to check. Default is null, meaning any action.
     * @return bool True if the action is currently being executed, false otherwise.
     */
    public function doing_action( $hook_name = null ) {
        return $this->doing_filter( $hook_name );
    }

    /**
     * Removes all callback functions from an action hook.
     *
     * @param string $hook_name The name of the action hook.
     * @param int|false $priority Optional. The priority number to remove. Default false, meaning all priorities.
     * @return bool True if the callbacks were successfully removed, false otherwise.
     */
    public function remove_all_actions( $hook_name, $priority = false ) {
        return $this->remove_all_filters( $hook_name, $priority );
    }

    /**
     * Calls all hooks associated with the 'all' hook.
     *
     * This function is used to call all hooks associated with the 'all' hook. It iterates through
     * all registered hooks and executes their callbacks if they are associated with the 'all' hook.
     * It's typically used by the `apply_filters` method to run all hooks before the specific
     * hook callbacks.
     *
     * @param array $args Arguments passed to the 'all' hook callbacks.
     */
    private function call_all_hook( $args ) {
        // Check if the 'all' hook exists
        if ( isset( $this->filter['all'] ) ) {
            // Loop through all callbacks associated with the 'all' hook
            foreach ( $this->filter['all'] as $priority => $callbacks ) {
                // Execute each callback with the provided arguments
                foreach ( $callbacks as $callback ) {
                    // Avoid the array_slice() if possible.
                    if ( 0 === $callback['accepted_args'] ) {
                        call_user_func( $callback['function'] );
                    } else {
                        call_user_func_array( $callback['function'], $args );
                    }
                }
            }
        }
    }

}