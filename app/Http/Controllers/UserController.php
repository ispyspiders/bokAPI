<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use function Avifinfo\read;

class UserController extends Controller
{
    // Hämta användare
    public function getUserInfo(string $id)
    {

        $user = User::find($id);

        // Om användare inte finns
        if (!$user) return response()->json(['message' => 'Ingen användare med valt id hittades'], 404);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'bio' => $user->bio,
            'current_read' => $user->current_read,
            'avatar_file' => $user->avatar_file,
            'avatar_url' => $user->avatar_url
        ]);
    }

    // uppdatera profil (PUT)
    public function updateProfile(Request $request, string $id)
    {
        // Läs in användare som ska uppdateras
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'message' => 'Ingen användare med id' + $id + 'hittades'
            ], 404);
        }

        // Läs in autentiserad användare
        $authUser = $request->user();
        if ($authUser->id !== $user->id) {
            return response()->json([
                'messsage' => 'Du saknar behörighet för att utföra denna åtgärd'
            ], 403);
        }

        // Validera ingående data
        $request->validate([
            'name' => 'required|string|max:255',
            'bio' => 'nullable|string',
            'current_read' => 'nullable|string',
            'avatar' => 'nullable|image|mimes:jpeg,jpg,png,gif|max:2048',
        ]);

        // variabler för bild
        $file_path = $user->avatar_file;
        $file_url = $user->avatar_url;

        // Om bildfil finns i request
        if ($request->hasFile('avatar')) {

            if ($request->file('avatar')->isValid() == false) {
                return response()->json([
                    'message' => 'Uppladding misslyckades på grund av ogiltig fil'
                ], 400);
            }

            // Ta bort gammal bild ur storage om det finns en
            if ($user->avatar_file != null) {
                
                // Kontroll att bild finns på disk
                if (Storage::disk(env('STORAGE_DRIVER', 'local'))->exists($user->avatar_file)) {
                    try {
                        Storage::disk(env('STORAGE_DRIVER', 'local'))->delete($user->avatar_file);
                        Log::info("Gammal avatar borttagen") . $user->avatar_file;
                    } catch (\Throwable $th) {
                        //TODO: hantera storage error till backend server log
                        Log::error("Misslyckades att ta bort gammal avatar: " . $user->avatar_file, ['exception' => $th]);
                    }
                }
            }

            $file_path = $request->file('avatar')->store('avatars', env('STORAGE_DRIVER', 'local'));

            // Fel vid uppladdning
            if (!$file_path) {
                return response()->json([
                    'message' => 'Uppladdning misslyckades på grund av ett internt fel'
                ], 500);
            }

            /** @var \Illuminate\Filesystem\FilesystemManager $disk */
            $disk = Storage::disk(env('STORAGE_DRIVER', 'local'));
            $file_url = $disk->url($file_path);
        }
        $user->update([
            'name' => $request->name,
            'bio' => $request->bio,
            'current_read' => $request->current_read,
            'avatar_file' => $file_path,
            'avatar_url' => $file_url
        ]);
        return $user;
    }

    // Radera avatar för användare
    public function deleteAvatar(Request $request, string $id)
    {
        // Läs in användare som avatar ska tas bort från
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'message' => 'Ingen användare med id' + $id + 'hittades'
            ], 404);
        }

        // Läs in autentiserad användare
        $authUser = $request->user();
        // Om användare är admin eller användaren som ska uppdateras
        if ($user->isAdmin() || $authUser->id == $user->id) {

            // Om en avatar finns lagrad
            if ($user->avatar_file == null) {
                return response()->json(['message' => 'Det finns ingen avatar att radera'], 400);
            }
            // Om avatar finns i storage
            if (Storage::disk(env('STORAGE_DRIVER', 'local'))->exists($user->avatar_file)) {
                try {
                    Storage::disk(env('STORAGE_DRIVER', 'local'))->delete($user->avatar_file);
                } catch (\Throwable $th) {
                    //TODO: hantera storage error till backend server log
                }
            }
            $user->update([
                'avatar_file' => null,
                'avatar_url' => null
            ]);
            return $user;
        } else {
            // unauthorized
            return response()->json([
                'messsage' => 'Du saknar behörighet för att utföra denna åtgärd'
            ], 403);
        }
    }
}
