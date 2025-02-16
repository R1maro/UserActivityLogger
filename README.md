Use it in your Laravel App(app/Helpers/{create a new PHP Class called UserActivityLogger })
<br>


// Authentication logs
<br>
UserActivityLogger::auth('login');
<br>
UserActivityLogger::auth('password_changed');
<br>

// Model CRUD operations
<br>
UserActivityLogger::viewed($post);
<br>
UserActivityLogger::created($user);
<br>

//before update your model take orginal values
<br>
UserAcitivityLogger::prepareForUpdate($product);
<br>
//after update get log
<br>
UserActivityLogger::updated($product);
<br>

UserActivityLogger::deleted($comment);
<br>
UserActivityLogger::restored($post); // For soft deletes
<br>

// Status changes
<br>
UserActivityLogger::status($user, 'suspend');
<br>
UserActivityLogger::status($post, 'approve');
<br>

// Data operations
<br>
UserActivityLogger::data('export', 'users');
<br>
UserActivityLogger::data('download', 'invoice', '123');
<br>

// Error logging
<br>
try {
<br>
// Some code
<br>
} catch (\Exception) {
<br>
UserActivityLogger::error('Payment processing failed', \Exception);
<br>
}
<br>
