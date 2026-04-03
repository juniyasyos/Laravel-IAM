<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check IAM admin access.
 * Uses configurable rules from config/iam.php
 */
class CheckIAMAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();
        $user = Auth::user();

        if (!$user) {
            \Log::warning('[CheckIAMAdmin] NO USER | Path: ' . $path . ' | Redirecting to login');
            return redirect()->route('login');
        }

        \Log::info('[CheckIAMAdmin] USER EXISTS | NIP: ' . $user->nip . ' | Path: ' . $path);

        // Check access based on config
        if (!$this->checkAccess($user)) {
            \Log::warning('[CheckIAMAdmin] ACCESS DENIED | NIP: ' . $user->nip . ' | Path: ' . $path . ' | User is NOT IAM admin');

            $message = config('iam.admin_access.denied_message', 'Access denied.');
            $redirect = config('iam.admin_access.denied_redirect');

            if ($redirect) {
                \Log::info('[CheckIAMAdmin] REDIRECTING TO: ' . $redirect);
                return redirect($redirect)->with('error', $message);
            }

            \Log::info('[CheckIAMAdmin] ABORTING WITH 403');
            abort(403, $message);
        }

        \Log::info('[CheckIAMAdmin] ACCESS GRANTED | NIP: ' . $user->nip . ' | Path: ' . $path . ' | User is IAM admin');
        return $next($request);
    }

    /**
     * Check if user has access based on configured rules.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    protected function checkAccess($user): bool
    {
        $rules = config('iam.admin_access.rules', []);

        if (empty($rules)) {
            \Log::debug('[CheckIAMAdmin::checkAccess] NO RULES configured | User: ' . $user->nip);
            return false;
        }

        $operator = config('iam.admin_access.operator', 'or'); // 'and' or 'or'

        $results = [];
        foreach ($rules as $index => $rule) {
            $result = $this->evaluateRule($user, $rule);
            $results[] = $result;
            $ruleType = $rule['type'] ?? 'unknown';
            \Log::debug('[CheckIAMAdmin::checkAccess] Rule [' . $index . '] type=' . $ruleType . ' | Result: ' . ($result ? 'PASS' : 'FAIL'));
        }

        $finalResult = $operator === 'and'
            ? !in_array(false, $results, true)
            : in_array(true, $results, true);

        \Log::debug('[CheckIAMAdmin::checkAccess] Operator: ' . $operator . ' | Final result: ' . ($finalResult ? 'ALLOWED' : 'DENIED'));

        return $finalResult;
    }

    /**
     * Evaluate a single access rule.
     *
     * @param  \App\Models\User  $user
     * @param  array  $rule
     * @return bool
     */
    protected function evaluateRule($user, array $rule): bool
    {
        $type = $rule['type'] ?? null;

        return match ($type) {
            'field' => $this->checkField($user, $rule),
            'field_in' => $this->checkFieldIn($user, $rule),
            'callback' => $this->checkCallbackRule($user, $rule),
            'role' => $this->checkRole($user, $rule),
            'permission' => $this->checkPermission($user, $rule),
            default => false,
        };
    }

    /**
     * Check if user field matches expected value.
     *
     * @param  \App\Models\User  $user
     * @param  array  $rule
     * @return bool
     */
    protected function checkField($user, array $rule): bool
    {
        $field = $rule['field'] ?? null;
        $value = $rule['value'] ?? null;
        $operator = $rule['operator'] ?? '=';

        if (!$field || !isset($user->$field)) {
            return false;
        }

        $fieldValue = $user->$field;

        return match ($operator) {
            '=' => $fieldValue == $value,
            '==' => $fieldValue === $value,
            '!=' => $fieldValue != $value,
            '!==' => $fieldValue !== $value,
            '>' => $fieldValue > $value,
            '>=' => $fieldValue >= $value,
            '<' => $fieldValue < $value,
            '<=' => $fieldValue <= $value,
            'contains' => str_contains($fieldValue, $value),
            'starts_with' => str_starts_with($fieldValue, $value),
            'ends_with' => str_ends_with($fieldValue, $value),
            default => false,
        };
    }

    /**
     * Check if user field value is in allowed list.
     *
     * @param  \App\Models\User  $user
     * @param  array  $rule
     * @return bool
     */
    protected function checkFieldIn($user, array $rule): bool
    {
        $field = $rule['field'] ?? null;
        $values = $rule['values'] ?? [];

        if (!$field || !isset($user->$field) || empty($values)) {
            return false;
        }

        return in_array($user->$field, $values, true);
    }

    /**
     * Check using custom callback from rule.
     *
     * @param  \App\Models\User  $user
     * @param  array  $rule
     * @return bool
     */
    protected function checkCallbackRule($user, array $rule): bool
    {
        $callback = $rule['callback'] ?? null;

        if (!is_callable($callback)) {
            return false;
        }

        return (bool) $callback($user);
    }

    /**
     * Check if user has specific role.
     *
     * @param  \App\Models\User  $user
     * @param  array  $rule
     * @return bool
     */
    protected function checkRole($user, array $rule): bool
    {
        $roleName = $rule['role'] ?? null;

        if (!$roleName || !method_exists($user, 'hasRole')) {
            return false;
        }

        return $user->hasRole($roleName);
    }

    /**
     * Check if user has specific permission.
     *
     * @param  \App\Models\User  $user
     * @param  array  $rule
     * @return bool
     */
    protected function checkPermission($user, array $rule): bool
    {
        $permission = $rule['permission'] ?? null;

        if (!$permission || !method_exists($user, 'can')) {
            return false;
        }

        return $user->can($permission);
    }
}
