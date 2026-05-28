<?php

Route::group( [ 'middleware' => 'web', 'prefix' => Helper::getSubdirectory(), 'namespace' => 'Modules\Cleanup\Http\Controllers' ], function () {
	$middleware = [ 'auth', 'roles' ];
	$roles      = [ 'admin' ];

	Route::get( '/cleanup', [
		'uses'       => 'CleanupController@index',
		'middleware' => $middleware,
		'roles'      => $roles,
	] )->name( 'cleanup.index' );

	Route::post( '/cleanup/conversations/preview', [
		'uses'       => 'CleanupController@previewConversations',
		'middleware' => $middleware,
		'roles'      => $roles,
	] )->name( 'cleanup.conversations.preview' );

	Route::post( '/cleanup/conversations/delete', [
		'uses'       => 'CleanupController@deleteConversations',
		'middleware' => $middleware,
		'roles'      => $roles,
	] )->name( 'cleanup.conversations.delete' );

	Route::post( '/cleanup/attachments/preview', [
		'uses'       => 'CleanupController@previewAttachments',
		'middleware' => $middleware,
		'roles'      => $roles,
	] )->name( 'cleanup.attachments.preview' );

	Route::post( '/cleanup/attachments/delete', [
		'uses'       => 'CleanupController@deleteAttachments',
		'middleware' => $middleware,
		'roles'      => $roles,
	] )->name( 'cleanup.attachments.delete' );
} );
