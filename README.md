Use it in your Laravel App(app/Helpers/{create a new PHP Class called UserActivityLogger })


// Authentication logs
UserActivityLogger::auth('login');
UserActivityLogger::auth('password_changed');

// Model CRUD operations
UserActivityLogger::viewed($post);
UserActivityLogger::created($user);
UserActivityLogger::updated($product);
UserActivityLogger::deleted($comment);
UserActivityLogger::restored($post); // For soft deletes

// Status changes
UserActivityLogger::status($user, 'suspend');
UserActivityLogger::status($post, 'approve');

// Data operations
UserActivityLogger::data('export', 'users');
UserActivityLogger::data('download', 'invoice', '123');

// Error logging
try {
// Some code
} catch (\Exception) {
UserActivityLogger::error('Payment processing failed', \Exception);
}