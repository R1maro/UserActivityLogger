<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UserActivityLogger
{
    /**
     * Message templates for different actions
     */
    private const MESSAGES = [
        'auth' => [
            'login' => 'Logged in successfully',
            'logout' => 'Logged out',
            'login_failed' => 'Failed login attempt',
            'password_reset' => 'Reset their password',
            'password_changed' => 'Changed their password',
            'two_factor_enabled' => 'Enabled two-factor authentication',
            'two_factor_disabled' => 'Disabled two-factor authentication',
        ],
        'crud' => [
            'view' => 'Viewed {model} #{id}',
            'create' => 'Created new {model} #{id}',
            'update' => 'Updated {model} #{id}: {changes}',
            'delete' => 'Deleted {model} #{id}',
            'restore' => 'Restored {model} #{id}',
            'force_delete' => 'Permanently deleted {model} #{id}',
        ],
        'data' => [
            'export' => 'Exported {model} data',
            'import' => 'Imported {model} data',
            'download' => 'Downloaded {model} #{id}',
            'upload' => 'Uploaded new {model}',
        ],
        'status' => [
            'activate' => 'Activated {model} #{id}',
            'deactivate' => 'Deactivated {model} #{id}',
            'approve' => 'Approved {model} #{id}',
            'reject' => 'Rejected {model} #{id}',
            'suspend' => 'Suspended {model} #{id}',
            'unsuspend' => 'Unsuspended {model} #{id}',
        ]
    ];

    /**
     * Log levels
     */
    private const LEVELS = ['info', 'warning', 'error', 'critical'];
    private const DEFAULT_LEVEL = 'info';

    /**
     * Log an authentication event
     */
    public static function auth(string $action, array $additional = []): void
    {
        if (!isset(self::MESSAGES['auth'][$action])) {
            throw new \InvalidArgumentException("Invalid auth action: {$action}");
        }

        self::log(
            "auth_{$action}",
            self::MESSAGES['auth'][$action],
            null,
            $additional
        );
    }

    /**
     * Log a model view event
     */
    public static function viewed(Model $model, array $additional = []): void
    {
        self::logModelAction($model, 'view', $additional);
    }

    /**
     * Log a model creation event
     */
    public static function created(Model $model, array $additional = []): void
    {
        // Include the model's fillable attributes in the log
        $attributes = array_intersect_key(
            $model->getAttributes(),
            array_flip($model->getFillable())
        );

        self::logModelAction(
            $model,
            'create',
            array_merge(['attributes' => $attributes], $additional)
        );
    }

    /**
     * Log a model update event
     */
    public static function updated(Model $model, array $additional = []): void
    {
        // Get old and new values for changed attributes
        $changes = $model->getChanges();
        $changeLog = [];
        foreach ($changes as $attribute => $newValue) {
            $changeLog[$attribute] = [
                'from' => $model->getOriginal($attribute),
                'to' => $newValue
            ];
        }

        self::logModelAction(
            $model,
            'update',
            array_merge(['changes' => $changeLog], $additional)
        );
    }

    /**
     * Log a model deletion event
     */
    public static function deleted(Model $model, array $additional = []): void
    {
        // Log the model's attributes before deletion
        $attributes = $model->getAttributes();

        self::logModelAction(
            $model,
            'delete',
            array_merge(['deleted_attributes' => $attributes], $additional)
        );
    }

    /**
     * Log a model restore event (for soft deletes)
     */
    public static function restored(Model $model, array $additional = []): void
    {
        self::logModelAction($model, 'restore', $additional);
    }

    /**
     * Log a model status change
     */
    public static function status(Model $model, string $action, array $additional = []): void
    {
        if (!isset(self::MESSAGES['status'][$action])) {
            throw new \InvalidArgumentException("Invalid status action: {$action}");
        }

        self::logModelAction($model, $action, $additional, 'status');
    }

    /**
     * Log a data operation
     */
    public static function data(string $action, string $modelType, ?string $modelId = null, array $additional = []): void
    {
        if (!isset(self::MESSAGES['data'][$action])) {
            throw new \InvalidArgumentException("Invalid data action: {$action}");
        }

        $message = self::MESSAGES['data'][$action];
        $message = str_replace('{model}', $modelType, $message);
        if ($modelId) {
            $message = str_replace('{id}', $modelId, $message);
        }

        self::log(
            "data_{$action}_{$modelType}",
            $message,
            null,
            $additional
        );
    }

    /**
     * Log an error event
     */
    public static function error(string $description, ?\Throwable $exception = null, array $additional = []): void
    {
        if ($exception) {
            $additional['exception'] = [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        self::log(
            'error',
            $description,
            null,
            $additional,
            'error'
        );
    }

    /**
     * Log a model action with a predefined message
     */
    private static function logModelAction(
        Model $model,
        string $action,
        array $additional = [],
        string $type = 'crud'
    ): void {
        if (!isset(self::MESSAGES[$type][$action])) {
            throw new \InvalidArgumentException("Invalid {$type} action: {$action}");
        }

        $modelName = class_basename($model);
        $message = self::MESSAGES[$type][$action];
        $message = str_replace('{model}', $modelName, $message);
        $message = str_replace('{id}', $model->getKey(), $message);

        // Add changes to message if it's an update action
        if ($action === 'update' && isset($additional['changes'])) {
            $changeDescriptions = [];
            foreach ($additional['changes'] as $field => $values) {
                $changeDescriptions[] = "$field from '{$values['from']}' to '{$values['to']}'";
            }
            $message = str_replace('{changes}', implode(', ', $changeDescriptions), $message);
        }

        self::log(
            "{$type}_{$action}_{$modelName}",
            $message,
            $model,
            $additional
        );
    }

    /**
     * Base log method
     */
    private static function log(
        string $action,
        string $description,
        ?Model $model = null,
        array $additional = [],
        string $level = self::DEFAULT_LEVEL
    ): void {
        $user = Auth::user();

        $logData = [
            'event' => [
                'action' => Str::snake($action),
                'description' => $description,
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ],
            'user' => [
                'id' => $user?->id ?? 'system',
                'email' => $user?->email ?? 'system',
                'ip' => request()->ip(),
            ],
            'request' => [
                'url' => request()->fullUrl(),
                'method' => request()->method(),
            ],
        ];

        if ($model) {
            $logData['model'] = [
                'type' => get_class($model),
                'id' => $model->getKey(),
            ];
        }

        if (!empty($additional)) {
            $logData['additional'] = $additional;
        }

        $level = in_array($level, self::LEVELS) ? $level : self::DEFAULT_LEVEL;
        Log::channel('user-activity')->$level($description, $logData);
    }
}
