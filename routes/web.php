<?php

use App\Events\NewMessage;
use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use App\Http\Controllers\ProfileController;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    $authUser = auth()->user();
    $users = User::query()->whereNot('id', $authUser->id)->get();
    return Inertia::render('Dashboard', compact('authUser', 'users'));
})
    ->middleware(['auth', 'verified'])->name('dashboard');




Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/send-message', function (Request $request) {
        $request->validate([
            'message' => ['required', 'string'],
            'receiver_id' => ['required'],
        ]);

        $sender_id = $request->user()->id;
        $receiver_id = $request->get('receiver_id');
        $message = $request->get('message');

        $chatMessage = ChatMessage::create([
            'sender_id' => $sender_id,
            'receiver_id' => $receiver_id,
            'message' => $message,
        ]);

        broadcast(new NewMessage($chatMessage));
        return response()->json($chatMessage);
    });

    Route::get('/get-messages/{id}', function ($id) {

        // SELECT * FROM chat_messages
        // where sender_id = 1 and receiver_id = 2
        // or where sender_id = 2 and receiver_id = 1

        $authId = auth()->id();
        return ChatMessage::query()
            ->where(function ($q) use ($authId, $id) {
                $q->where('sender_id', $authId)
                    ->where('receiver_id', $id);
            })
            ->orWhere(function ($q) use ($authId, $id) {
                $q->where('sender_id', $id)
                    ->where('receiver_id', $authId);
            })
            ->get();
    });
});

require __DIR__ . '/auth.php';
