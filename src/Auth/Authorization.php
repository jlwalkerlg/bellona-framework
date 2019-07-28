<?php

namespace Bellona\Auth;

use App\Models\User;
use Bellona\Support\Facades\Auth;

class Authorization
{
    /** @var array $policyInstances Already instantiated policy classes. */
    private $policyInstances = [];


    /**
     * Determine if an action is permitted.
     *
     * @param string $action Policy method to run.
     * @param string|Model $model Model instance or name of policy class.
     */
    public function can(string $action, $model, User $user = null)
    {
        if (is_string($model)) {
            $modelClass = $model;
        } else {
            $modelClass = get_class($model);
        }

        $policyClass = substr($modelClass, strrpos($modelClass, '\\')) . 'Policy';
        $policyClass = 'App\Policies\\' . ltrim($policyClass, '\\');

        if (!isset($this->policyInstances[$policyClass])) {
            $this->policyInstances[$policyClass] = new $policyClass;
        }

        $policyInstance = $this->policyInstances[$policyClass];

        $user = $user ?? $this->resolvePolicyUser($policyInstance, $action);

        if ($user === false) return false;

        return $policyInstance->$action($user, $model);
    }


    private function resolvePolicyUser($policyInstance, $action)
    {
        $reflector = new \ReflectionMethod($policyInstance, $action);
        $params = $reflector->getParameters();
        $userParam = $params[0];

        $user = Auth::user();

        if ($userParam->allowsNull()) {
            return $user;
        }

        return $user ?? false;
    }
}
