<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Models\Group;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Organisation;
use App\Models\Residence;
use App\Models\Tag;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Mailer\Event\MessageEvent;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Error\Notice;
use Illuminate\Support\Str;

use function PHPUnit\Framework\isEmpty;

class ApiController extends Controller
{
    public function loginSanctum(Request $request)
    {
        $credentials = $request->only('email', 'password');
        if (Auth::attempt($credentials)) {
            return response(['message' => 'The user has been authenticated successfully'], 200);
        }
        return response(['message' => 'The provided credentials do not match our records.'], 401);
    }

    public function logoutSanctum(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        return response(['message' => 'The user has been logged out successfully'], 200);
    }

    public function getOrganisations()
    {
        // Voorbeeld return
        /* 
        data: {
            0: {
                id: 1,
                name: organisation_name,
                description: qsfoimjqsifjsiejqilfjseqlifi,
                users_id: 5,
            },
            1: {
                id: 2,
                name: organisation_name,
                description: qsfoimjqsifjsiejqilfjseqlifi,
                users_id: 8,
            }
        }
        */

        $organisations = Organisation::with('user')->get();

        foreach ($organisations as $organisation) {
            $user = $organisation->user;

            if (!is_null($user->profile_picture)) {
                $user->profile_picture = rtrim(env('APP_URL'), '/') . '/storage/' . str_replace('storage/app/public', '', $user->profile_picture);
            }

            if (!is_null($user->banner_picture)) {
                $user->banner_picture = rtrim(env('APP_URL'), '/') . '/storage/' . str_replace('storage/app/public', '', $user->banner_picture);
            }
        }

        return response(['data' => $organisations->makeHidden(['user_id'])], 200);
    }

    public function getOrganisation(Organisation $organisation)
    {
        // Voorbeeld return
        /* 
        data: {
            id: 1,
            name: organisation_name,
            description: msqjfilsjqemlifjqsmejfilmqse,
            users_id: 5,
            groups: {
                0: {
                    id: 18,
                    name: name,
                    description: mlqsijfilmqsjefilqjsef,
                },
                1: {
                    id: 26,
                    name: name,
                    description: mlqsijfilmqsjefilqjsef,
                },
                2: {
                    id: 37,
                    name: name,
                    description: mlqsijfilmqsjefilqjsef,
                },
            }
        }
        */
        $response = Organisation::with('user')->where('id', $organisation->id)->with('groups')->first();
        $response->user->banner_picture ? 
        $response->user->banner_picture = rtrim(env('APP_URL'), '/') . '/storage/' . str_replace('storage/app/public', '', $response->user->banner_picture) :
        null;
        $response->user->profile_picture ? 
        $response->user->profile_picture = rtrim(env('APP_URL'), '/') . '/storage/' . str_replace('storage/app/public', '', $response->user->profile_picture) :
        null;
        return response(["data" => $response], 200);
    }

    public function getSubgroup(Group $group)
    {
        /*
        data: {
            id: 18,
            name: name,
            description: mlqsijfilmqsjefilqjsef,
            subscribers: {
                0: {
                    id: 12,
                    first_name: nmqfjsilf,
                    last_name: msqjfqislef,
                    image: localhost:8080/assets/children/misqjfmiesjf.jpg,
                },
                1: {
                    id: 19,
                    first_name: nmqfjsilf,
                    last_name: msqjfqislef,
                    image: localhost:8080/assets/children/misqjfmiesjf.jpg,
                }
            }
        }
        */
        $group = Group::with('subscribers')->where('id', $group->id)->get();

        return response(["data" => $group], 200);
    }

    public function addSubgroup(Request $request)
    {
        /*
        message: "Group {name} has been added succesfully."
        */
        $request->validate([
            'name' => 'required|string|min:3',
            'description' => 'required|string|min:5'
        ]);

        $organisation = Organisation::where('user_id', Auth::user()->id)->first();

        $new_group = Group::create([
            'name' => $request->name,
            'description' => $request->description,
            'organisation_id' => $organisation->id,
        ]);

        return response(['data' => 'Group {' . $new_group->name . '} has been created succesfully'], 200);
    }

    public function getWaitlist(Group $group)
    {
        /*
        * IEDEREEN HIER MOET GEACCEPTEERD OF VERWIJDERD WORDEN WORDEN *
        data: {
            0: {
                id: 12,
                first_name: nmqfjsilf,
                last_name: msqjfqislef,
                image: localhost:8080/assets/children/misqjfmiesjf.jpg,
            },
            1: {
                id: 19,
                first_name: nmqfjsilf,
                last_name: msqjfqislef,
                image: localhost:8080/assets/children/misqjfmiesjf.jpg,
            }
        }
        */

        $organisation = Organisation::where('user_id', Auth::user()->id)->first();
        $waitlist = Group::with('waitlist')->where('organisation_id', $organisation->id)->where('id', $group->id)->first();

        return response(['data' => $waitlist->waitlist], 200);
    }

    public function updateWaitlister(Request $request, Group $group, Child $child)
    {
        /*
        message: "The status of user with name {first_name} {last_name} has been updated to: {status} succesfully"
        */
        $this->authorize('update', $group);
        $request->validate([
            'status' => 'required|in:sent,accepted,denied'
        ]);

        // Check 1: User heeft een groep met de id $group->id
        $organisation = Organisation::where('user_id', Auth::user()->id)->first();
        $new_group = Group::where('id', $group->id)->where('organisation_id', $organisation->id)->first();
        if (!$new_group) {
            return response(['message' => 'No access granted'], 403);
        }

        // Check 2: Er bestaat een veld waarvan group_id en child_id bovenstaande 2 parameters zijn
        $exists = $new_group->children()->where('child_id', $child->id)->exists();
        if (!$exists) {
            return response(['message' => 'No access granted'], 403);
        }

        // Indien deze checks goed zijn -> update de value naar status
        $new_group->children()->updateExistingPivot($child->id, ['subscribed' => $request->status]);

        return response(['message' => 'The status of ' . $child->first_name . ' ' . $child->last_name . ' has been updated to: ' . $request->status . ' successfully.'], 200);
    }

    public function getUser()
    {
        /*
        data: {
            id: 1,
            first_name: lmqsjfl,
            last_name: lqsmjfe,
            email: lmsqfjm.isj@gmail.com,
            created_at: 2023-12-18 12:23:32,
            updated_at: 2023-12-18 12:23:32,
            role: user,
            profile_picture: localhost:8080/assets/smqijfiseji.jpg,
            banner_picture: null,
            status: offline,
            residence: {
                id: 23,
                city: Destelbergen,
                zip: 9070,
                streetname: wolfputstraat,
                country: Belgium,
                number: 43,
            }
        }
        */
        $user = User::with('residence')->where('id', Auth::user()->id)->first();
        if($user->role == 'organisation') {
            $user = User::with(['residence', 'organisation'])->where('id', Auth::user()->id)->first();
        }
        $user->profile_picture ?
            $user->profile_picture = rtrim(env('APP_URL'), '/') . '/storage/' . str_replace('storage/app/public', '', $user->profile_picture) :
            null;
        $user->banner_picture ?
            $user->banner_picture = rtrim(env('APP_URL'), '/') . '/storage/' . str_replace('storage/app/public', '', $user->banner_picture) :
            null;
        return response(['data' => $user], 200);
    }

    public function addUser(Request $request)
    {
        // message: "User {first_name} {last_name} as {role} has been added succesfully"
        $json = Http::get('https://restcountries.com/v3.1/all')->body();
        $countries = json_decode($json, true);
        $countriesList = [];
        foreach ($countries as $key => $value) {
            $countriesList[$key]  = $value['name']['common'];
        }
        $request->validate([
            // Residence
            'residence' => 'array',
            'residence.city' => 'required|string|min:2',
            'residence.zip' => 'required|string|max:10',
            'residence.streetname' => 'required|max:100',
            'residence.country' => 'required|in:' . implode(',', $countriesList),
            'residence.number' => 'required|max:10',

            // User
            'user' => 'array',
            'user.first_name' => 'required|string',
            'user.last_name' => 'required|string',
            'user.email' => 'required|email:rfc,dns',
            'user.password' => 'required',
            'user.role' => 'in:user,organisation',
            'profile_picture' => 'required|image',
            'banner_picture' => 'required|image',
        ]);

        $residence = Residence::create([
            'city' => $request->residence->city,
            'zip' => $request->residence->zip,
            'street' => $request->residence->street,
            'country' => $request->residence->country,
            'number' => $request->residence->number,
            'created_at' => Carbon::now()->format('Y-m-d H:i:s')
        ]);

        $profile_path = null;
        if ($request->hasFile('profile_picture')) {
            $file = $request->file('profile_picture');
            $profile_path = $file->store('users/profile_pictures');
        }

        $banner_path = null;
        // Bestand verwerken
        if ($request->hasFile('banner_picture')) {
            $file = $request->file('banner_picture');
            $banner_path = $file->store('app/public/users/banner_pictures/');
        }

        $user = User::create([
            'first_name' => $request->user->first_name,
            'last_name' => $request->user->last_name,
            'email' => $request->user->email,
            'password' => Hash::make($request->user->password),
            'role' => $request->user->role,
            'profile_picture' => 'storage' . $profile_path,
            'banner_picture' => 'storage' . $banner_path,
            'status' => 'off',
            'residence_id' => $residence->id,
            'created_at' => Carbon::now()->format('Y-m-d H:i:s')
        ]);

        // If it is an organisation
        if ($request->user->role == 'organisation') {
            $request->validate([
                'organisation.name' => 'required|string',
                'organisation.description' => 'string'
            ]);
            Organisation::create([
                'name' => $request->organisation->name,
                'organisation_id' => Str::random(24),
                'description' => $request->organisation->description ? $request->organisation->description : null,
                'user_id' => $user->id,
            ]);
        }

        return response(['data' => $request->first_name . ' ' . $request->last_name . ' as ' . $request->role . ' has been added succesfully'], 200);
    }

    public function updateUser(Request $request)
    {
        // message: "User {first_name} {last_name} has been updated succesfully"

        $countriesList = [];
        if ($request->country) {
            $json = Http::get('https://restcountries.com/v3.1/all')->body();
            $countries = json_decode($json, true);
            foreach ($countries as $key => $value) {
                $countriesList[$key]  = $value['name']['common'];
            }
        }
        $request->validate([
            // Residence
            'residence' => 'array',
            'residence.city' => 'string|min:2',
            'residence.zip' => 'string|max:10',
            'residence.streetname' => 'max:100',
            'residence.country' => $request->country ? 'in:' . implode(',', $countriesList) : '',
            'residence.number' => 'max:10',

            // User
            'user' => 'array',
            'user.first_name' => 'string',
            'user.last_name' => 'string',
            'user.email' => 'email:rfc,dns',
            'user.role' => 'in:user,organisation',
            'profile_picture' => 'image',
            'banner_picture' => 'image',
        ]);

        $profile_path = null;
        if ($request->hasFile('profile_picture')) {
            $file = $request->file('profile_picture');
            $profile_path = $file->store('users/profile_pictures');
        }

        $banner_path = null;
        // Bestand verwerken
        if ($request->hasFile('banner_picture')) {
            $file = $request->file('banner_picture');
            $banner_path = $file->store('app/public/users/banner_pictures/');
        }

        $user = Auth::user();
        User::where('id', Auth::user()->id)
            ->update([
                'first_name' => $request->user->first_name ? $request->user->first_name : $user->first_name,
                'last_name' => $request->user->last_name ? $request->user->last_name : $user->last_name,
                'email' => $request->user->email ? $request->user->email : $user->email,
                'password' => $request->user->password ? Hash::make($request->user->password) : $user->password,
                'profile_picture' => $request->profile_picture ? $profile_path : $user->profile_picture,
                'banner_picture' => $request->banner_picture ? $banner_path : $user->banner_picture,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s')
            ]);

        $residence = Residence::where('id', $user->residence_id)->first();
        $residence
            ->update([
                'city' => $request->residence->city ? $request->residence->city : $residence->city,
                'zip' => $request->residence->zip ? $request->residence->zip : $residence->zip,
                'streetname' => $request->residence->streetname ? $request->residence->streetname : $residence->streetname,
                'country' => $request->residence->country ? $request->residence->country : $residence->country,
                'number' => $request->residence->number ? $request->residence->number : $residence->number,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s')
            ]);

        return response(['data' => 'User ' . Auth::user()->first_name . ' ' . Auth::user()->last_name . ' has been updated succesfully'], 200);
    }

    public function getNotifications(Request $request, Group $group)
    {
        /*
        data: {
            id: 1,
            message: "Dit wordt een leuke activiteit met school, we gaan ...",
            created_at: 2023-12-18 12:23:32,
            updated_at: 2023-12-18 12:23:32,
            deadline: null,
            duration: 2:30:00,
            image: localhost:8080/assets/smqijflqefmiljs.jpg,
            type: information,
            event: 0,
            obligatory: 0,
            group: {
                id: 89,
                name: De Vlindertjes,
                organisation: {
                    id: 34,
                    name: Basisschool de regenboog
                }
            }
            tags: {
                0: {
                    name: zwemmen,
                    color: #2A2A2A,
                },
                1: {
                    name: uitstap,
                    color: #919191,
                }
            }
        }
        */

        $notifications = [];

        if ($request->id) {
            $request->validate([
                'id' => 'exists:notifications'
            ]);
        }

        if (Auth::user()->role == 'organisation') {
            $org = Organisation::where('user_id', Auth::user()->id)->first();
            $new_group = Group::find($group->id);
            if ($new_group === null) {
                return response(['message' => 'Access not granted'], 403);
            }

            if ($request->id) {
                $notifications = Notification::with(['group', 'tags'])
                    ->where('group_id', $group->id)
                    ->findOrFail($request->id);

                if ($notifications->group->id != $request->id) {
                    return response(['message' => 'Access not granted'], 403);
                }
            } else {
                $notifications = Notification::where('group_id', $group->id)->get();
            }
        } else {
            $request->validate([
                'child_id' => 'required|exists:children,id'
            ]);

            $child = Child::with('groups')
                ->where('user_id', Auth::user()->id)
                ->findOrFail($request->child_id);

            $subscription_ids = $child->groups->pluck('id')->toArray();

            if (!$subscription_ids->contains($group->id)) {
                return response(['message' => 'Not subscribed'], 403);
            }

            $notifications = Notification::with(['children', 'group', 'tags'])
                ->where('group_id', $group->id)
                ->whereHas('children', function ($query) use ($request) {
                    $query->where('id', $request->child_id);
                });

            if (!$request->id) {
                $notifications = $notifications
                    ->get()
                    ->makeHidden(['group_id', 'group', 'tags']);
            } else {
                $notifications = $notifications
                    ->findOrFail($request->id);
            }

            $notifications->makeHidden('children');
        }

        return response(['data' => $notifications], 200);
    }

    public function getNotificationsChild(int $id) {
        // Checken of kind wel van de ouder is:
        $new_child = Child::with('groups')
        ->where('user_id', Auth::user()->id)
        ->findOrFail($id);

        $notifications = Child::with(['notSeenNotifications.group'])
        ->where('id', $id)
        ->first()
        ->notSeenNotifications;

        foreach ($notifications as $index => $notification) {
            $notification['image'] = rtrim(env('APP_URL'), '/') . '/storage/' . str_replace('/storage/app/public', '', $notification->image);
            // 1. Algemene check:
            $check = DB::table('groups_has_children')
                ->where('group_id', $notification->group_id)
                ->where('child_id', $new_child->id)
                ->where('subscribed', 'accepted')
                ->first();
            if (!$check) {
                unset($notifications[$index]);
            }
        }

        return response(['data' => $notifications->makeHidden(['pivot', 'group_id'])], 200);
    }

    public function getAllNotificationsChild(int $id) {
        // Checken of kind wel van de ouder is:
        $new_child = Child::with('groups')
        ->where('user_id', Auth::user()->id)
        ->findOrFail($id);

        $notifications = Child::with(['notifications'])
        ->where('id', $id)
        ->first()
        ->notifications;

        foreach ($notifications as $index => $notification) {
            $notification['image'] = rtrim(env('APP_URL'), '/') . '/storage/' . str_replace('/storage/app/public', '', $notification->image);
            // 1. Algemene check:
            $check = DB::table('groups_has_children')
                ->where('group_id', $notification->group_id)
                ->where('child_id', $new_child->id)
                ->where('subscribed', 'accepted')
                ->first();
            if (!$check) {
                unset($notifications[$index]);
            }
        }

        return response(['data' => $notifications->makeHidden(['pivot', 'group_id'])], 200);
    }

    public function getAllTodoChild(int $id) {
        // Checken of kind wel van de ouder is:
        $new_child = Child::with('groups')
        ->where('user_id', Auth::user()->id)
        ->findOrFail($id);

        $notifications = Child::with(['todoNotifications'])
        ->where('id', $id)
        ->first()
        ->todoNotifications;

        foreach ($notifications as $index => $notification) {
            $notification['image'] = rtrim(env('APP_URL'), '/') . '/storage/' . str_replace('/storage/app/public', '', $notification->image);
            // 1. Algemene check:
            $check = DB::table('groups_has_children')
                ->where('group_id', $notification->group_id)
                ->where('child_id', $new_child->id)
                ->where('subscribed', 'accepted')
                ->first();
            if (!$check) {
                unset($notifications[$index]);
            }
        }


        return response(['data' => $notifications], 200);
    }

    public function addNotification(Request $request, Group $group)
    {
        $request->validate([
            'message' => 'required',
            'deadline' => 'date_format:Y-m-d H:i:s',
            'duration' => 'date_format:H:i:s',
            'image' => 'image',
            'type' => 'required|in:todo,information',
            'event' => 'between:0,1',
            'obligatory' => 'between:0,1',
            $group->id => 'exists:groups,id'
        ]);

        $image = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $image = $file->store('notifications');
        }

        $new_not = Notification::create([
            'message' => $request->message,
            'deadline' => $request->deadline ? $request->deadline : null,
            'duration' => $request->duration ? $request->duration : null,
            'image' => $request->image ? $image : null,
            'type' => $request->type,
            'event' => $request->event ? $request->event : 0,
            'obligatory' => $request->obligatory ? $request->obligatory : 0,
            'group_id' => $group->id
        ]);

        $children = Child::whereHas('groups', function ($query) {
            $query->where('groups_has_children.subscribed', '=', 'accepted');
        })->pluck('id');              

        $new_not->children()->attach($children, ['seen' => 0, 'filled_in' => 0]);

        return response(['message' => 'Notification {' . $new_not->id  . '} succesfully created'], 200);
    }

    public function updateNotification(Request $request, Group $group, Notification $notification)
    {
        // message: "Notification {id} succesfully updated"
        $this->authorize('update', $group);
        $this->authorize('update', $notification);


        $notification = Notification::findOrFail($notification->id);
        $group = $notification->group;
        $organisation = $group->organisation;

        if ($organisation->user_id === Auth::user()->id) {
            $notification->update([
                'message' => $request->message,
                'deadline' => $request->deadline,
                'duration' => $request->duration,
                'image' => $request->image,
                'type' => $request->type,
                'event' => $request->event,
                'obligatory' => $request->obligatory,
            ]);
        } else {
            return response(['message' => 'Access not granted'], 403);
        }

        return response(['message' => 'Notification {' . $request->id  . '} succesfully updated'], 200);
    }
    
    public function updateSeen(Request $request)
    {
        $request->validate([
            'notification_id' => 'required|exists:notifications,id',
            'child_id' => 'required|exists:children,id',
        ]);

        // message: "Notification {id} succesfully updated"
        $child = Child::where('user_id', Auth::user()->id)->findOrFail($request->child_id);
        
        $table = DB::table('notifications_has_children')
        ->where('child_id', $request->child_id)
        ->where('notification_id', $request->notification_id);

        if ($table->first()) {
            // 1. Algemene check:
            $check = DB::table('groups_has_children')
                ->where('group_id', Notification::where('id', $request->notification_id)->pluck('group_id')->first())
                ->where('child_id', $request->child_id)
                ->where('subscribed', 'accepted')
                ->first();
            if (!$check) {
                return response(['Acces not granted'], 403);
            }

            $table->update([
                'seen' => 1
            ]);
        } else {
            return response(['message' => 'Access not granted'], 403);
        }

        return response(['message' => 'Notification succesfully updated'], 200);
    }
    public function updateFilled(Request $request)
    {
        $request->validate([
            'notification_id' => 'required|exists:notifications,id',
            'child_id' => 'required|exists:children,id',
        ]);

        // message: "Notification {id} succesfully updated"
        $child = Child::where('user_id', Auth::user()->id)->findOrFail($request->child_id);
        
        $table = DB::table('notifications_has_children')
        ->where('child_id', $request->child_id)
        ->where('notification_id', $request->notification_id);

        if ($table->first()) {
            // 1. Algemene check:
            $check = DB::table('groups_has_children')
                ->where('group_id', Notification::where('id', $request->notification_id)->pluck('group_id')->first())
                ->where('child_id', $request->child_id)
                ->where('subscribed', 'accepted')
                ->first();
            if (!$check) {
                return response(['Acces not granted'], 403);
            }

            $table->update([
                'filled_in' => 1
            ]);
        } else {
            return response(['message' => 'Access not granted'], 403);
        }

        return response(['message' => 'Notification succesfully updated'], 200);
    }

    public function getMessages()
    {
        // Voorbeeld return organisatie
        /* 
        data: {
            name_group: {
                0: {
                    from: first_name last_name,
                    to: first_name last_name,
                    receiver_image_url: localhost:8080/assets/smliqjfmiljse38mljmls12.jpg,
                    text: ellipsis (100char) ...
                },
                1: {
                    from: first_name last_name,
                    to: first_name last_name,
                    receiver_image_url: localhost:8080/assets/smliqjfmiljse38mljmls12.jpg,
                    text: ellipsis (100char) ...
                }
            },
            name_group: {
                0: {
                    from: first_name last_name,
                    to: first_name last_name,
                    receiver_image_url: localhost:8080/assets/smliqjfmiljse38mljmls12.jpg,
                    text: ellipsis (100char) ...
                }
            },
        }
        */
        // Voorbeeld return user
        /* 
        data: {
            name_child: {
                0: {
                    from: first_name last_name,
                    to: first_name last_name,
                    receiver_image_url: localhost:8080/assets/smliqjfmiljse38mljmls12.jpg,
                    text: ellipsis (100char) ...
                },
                1: {
                    from: first_name last_name,
                    to: first_name last_name,
                    receiver_image_url: localhost:8080/assets/smliqjfmiljse38mljmls12.jpg,
                    text: ellipsis (100char) ...
                }
            },
            name_other_child: {
                0: {
                    from: first_name last_name,
                    to: first_name last_name,
                    receiver_image_url: localhost:8080/assets/smliqjfmiljse38mljmls12.jpg,
                    text: ellipsis (100char) ...
                }
            }
        }
        */

        $user = Auth::user();

        $groupedMessages = [];
        if ($user->role === 'organisation') {
            $organisation = Organisation::where('user_id', $user->id)->first();

            $messages = Message::whereHas('group', function ($query) use ($organisation) {
                $query->where('organisation_id', $organisation->id);
            })->get();
        } else if ($user->role === 'user') {
            $children = Child::where('user_id', $user->id)->pluck('id');

            $messages = Message::whereIn('child_id', $children)
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($messages as $message) {
                $childId = $message['child_id'];
                $groupId = $message['group_id'];
                if (!isset($groupedMessages[$childId])) {
                    $groupedMessages[$childId] = [];
                }
                if (!isset($groupedMessages[$childId][$groupId])) {
                    $groupedMessages[$childId][$groupId] = [];
                }
                $groupedMessages[$childId][$groupId][] = $message;
            }
        } else {
            return response(['message' => 'Invalid user role'], 403);
        }

        $formattedMessages = [];
        if ($user->role === 'organisation') {
            foreach ($messages as $message) {
                $groupName = $message->group->name;

                if (!isset($formattedMessages[$groupName])) {
                    $formattedMessages[$groupName] = [];
                }
                $childId = $message->child_id;
                if (!isset($formattedMessages[$groupName][$childId])) {
                    $formattedMessages[$groupName][$childId] = [
                        'from' => $message->child->first_name . ' ' . $message->child->last_name,
                        'to' => $message->group->organisation->user->first_name . ' ' . $message->group->organisation->user->last_name,
                        'receiver_image_url' => rtrim(env('APP_URL'), '/') . '/storage' . str_replace('/storage/app/public', '', $message->child->image),
                        'text' => strlen($message->message) > 100 ? substr($message->message, 0, 100) . ' ...' : $message->message,
                    ];
                } else {
                    if ($message->created_at > $formattedMessages[$groupName][$childId]['created_at']) {
                        $formattedMessages[$groupName][$childId] = [
                            'from' => $message->child->user->first_name . ' ' . $message->child->user->last_name,
                            'to' => $message->group->organisation->user->first_name . ' ' . $message->group->organisation->user->last_name,
                            'receiver_image_url' => $message->child->image ? rtrim(env('APP_URL'), '/') . '/storage/' . str_replace('/storage/app/public', '', $message->child->image) : null,
                            'text' => strlen($message->message) > 100 ? substr($message->message, 0, 100) . ' ...' : $message->message,
                        ];
                    }
                }
            }
        } else {
            foreach ($messages as $message) {
                $child_first_name = $message->child->id;
                $groupId = $message['group_id'];
                $from = $message->child->first_name . ' ' . $message->child->last_name;
                $to = $message->group->organisation->user->first_name . ' ' . $message->group->organisation->user->last_name;
                $receiverImageUrl = $message->child->image ? rtrim(env('APP_URL'), '/') . '/storage/' . str_replace('/storage/app/public', '', $message->child->image) : null;
                $text = strlen($message->message) > 100 ? substr($message->message, 0, 100) . ' ...' : $message->message;
                if (!isset($formattedMessages[$child_first_name])) {
                    $formattedMessages[$child_first_name] = [];
                }
                if (!isset($formattedMessages[$child_first_name][$groupId])) {
                    $formattedMessages[$child_first_name][$groupId] = [];
                }

                $formattedMessages[$child_first_name][$groupId] = [
                    'from' => $from,
                    'to' => $to,
                    'receiver_image_url' => $receiverImageUrl,
                    'text' => $text,
                ];
            }
        }

        return response(['data' => $formattedMessages], 200);
    }

    public function getMessage(int $id)
    {
        // Voorbeeld return met pagination met recentste berichten eerst
        /* 
        data: {
            {
                from: first_name last_name,
                to: first_name last_name,
                receiver_image_url: localhost:8080/assets/smliqjfmiljse38mljmls12.jpg,
                text: full_text
            },
            {
                from: first_name last_name,
                to: first_name last_name,
                receiver_image_url: localhost:8080/assets/smliqjfmiljse38mljmls12.jpg,
                text: full_text
            }
        },
        this_page = 2,
        next_page = 2,

        */

        $user = Auth::user();
        $messages = [];
        $groupedMessages = [];
        if ($user->role === 'user') {
            $child = Child::where('user_id', $user->id)->findOrFail($id);
            $messages = Message::where('child_id', $child->id)
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($messages as $message) {
                $groupId = $message->group->id;

                if (!isset($groupedMessages[$groupId])) {
                    $groupedMessages[$groupId] = [];
                }
                $from = $message->child->name;
                $to = $message->group->name;
                $receiverImageUrl = $message->child->image ? rtrim(env('APP_URL'), '/') . '/storage/' . str_replace('/storage/app/public', '', $message->child->image) : null;
                $groupedMessages[$groupId][] = [
                    'from' => $from,
                    'to' => $to,
                    'receiver_img' => $receiverImageUrl,
                    'text' => $message->message,
                    'sent' => $message->created_at
                ];
            }
        } else if ($user->role === 'organisation') {
            $organisation = Organisation::where('user_id', $user->id)->first();

            $messages = Message::whereHas('group', function ($query) use ($organisation) {
                $query
                    ->where('organisation_id', $organisation->id);
            })
                ->where('child_id', $id)
                ->orderBy('id', 'desc')
                ->get();

            foreach ($messages as $message) {
                $groupId = $message->group->name;

                if (!isset($groupedMessages[$groupId])) {
                    $groupedMessages[$groupId] = [];
                }
                $from = $message->child->first_name . ' ' . $message->child->last_name;
                $to = $message->group->organisation->user->first_name . ' ' . $message->group->organisation->user->last_name;
                $receiverImageUrl = $message->child->image ? rtrim(env('APP_URL'), '/') . '/storage/' . str_replace('/storage/app/public', '', $message->child->image) : null;
                $groupedMessages[$groupId][] = [
                    'from' => $from,
                    'to' => $to,
                    'receiver_img' => $receiverImageUrl,
                    'text' => $message->message,
                    'sent' => $message->created_at
                ];
            }
        } else {
            return response(['message' => 'Invalid user role'], 403);
        }
        if (sizeof($messages) > 0) {
            $messages = $groupedMessages;
            return response(['data' => $messages], 200);
        } else {
            return response(['message' => 'Messages not found'], 404);
        }
    }

    public function addMessage(Request $request)
    {
        // TODO: message versturen
        $request->validate([
            'message' => 'required|max:1000',
            'group_id' => 'required|exists:groups,id',
            'child_id' => 'required|exists:children,id'
        ]);

        $user = Auth::user();

        // Checks
        // 1. Algemene check:
        $check = DB::table('groups_has_children')
            ->where('group_id', $request->group_id)
            ->where('child_id', $request->child_id)
            ->where('subscribed', 'accepted')
            ->first();
        if (!$check) {
            return response(['Acces not granted'], 403);
        }
        // 2. Role afhankelijke checks
        if ($user->role === 'user') {
            Child::where('user_id', $user->id)->findOrFail($request->child_id);
        } else {
            $organisation = Organisation::where('user_id', $user->id)->first();
            Group::where('organisation_id', $organisation->id)->findOrFail($request->group_id);
        }

        // Alles goed, maak nieuwe message:
        Message::create([
            'message' => $request->message,
            'group_id' => $request->group_id,
            'child_id' => $request->child_id,
            'created_at' => Carbon::now()->format('Y-m-d H:i:s') . '',
        ]);
        return response(['message' => 'The message has been created succesfully'], 200);
    }

    public function getStatus(int $id)
    {
        // Alleen als je er een chat mee hebt mag je de status ophalen als organisatie
        // Eigen status moet nooit opgehaald kunnen worden want je kan het toch alleen maar zien als je zelf ONLINE bent
        $user = User::findOrFail($id);
        return response(['data' => $user->status], 200);
    }

    public function updateStatus(Request $request)
    {
        $request->validate([
            'status' => 'required|in:on,off'
        ]);
        $user = Auth::user();
        $user->update(['status' => $request->status]);

        return response(['message' => 'Status succesfully updated'], 200);
    }

    public function getSubscriptions()
    {
        $user = Auth::user();
        $children = Child::where('user_id', $user->id)->pluck('id');
        $subscriptions = DB::table('groups_has_children')
            ->whereIn('child_id', $children)
            ->where('subscribed', 'accepted')
            ->get();

        $data = [];
        $allChildren = Child::whereIn('id', $children)->get();
        foreach ($allChildren as $child) {
            $name = $child->name;
            if (!isset($data[$name])) {
                $data[$name] = [];
            }
            foreach ($subscriptions as $subscription) {
                if ($subscription->child_id == $child->id) {
                    $data[$name][] = Group::findOrFail($subscription->group_id);
                }
            }
        }
        return response(['data' => $data], 200);
    }

    public function getAllSubscriptions()
    {
        $user = Auth::user();
        $children = Child::where('user_id', $user->id)->pluck('id');
        $subscriptions = DB::table('groups_has_children')
            ->whereIn('child_id', $children)
            ->where(function ($query) {
                $query->where('subscribed', 'accepted')
                    ->orWhere('subscribed', 'sent');
            })
            ->get();


        $data = [];
        $allChildren = Child::whereIn('id', $children)->get();
        foreach ($allChildren as $child) {
            $name = $child->name;
            if (!isset($data[$name])) {
                $data[$name] = [];
            }
            $int = 0;
            foreach ($subscriptions as $subscription) {
                if ($subscription->child_id == $child->id) {
                    $data[$name][$int] = Group::findOrFail($subscription->group_id);
                    $data[$name][$int]['subscribed'] = $subscription->subscribed;
                    $int++;
                }
            }
        }
        return response(['data' => $data], 200);
    }

    public function addSubscription(Request $request)
    {
        // TODO: Check of al bestaat, zo ja zet de denied terug op sent.
        $request->validate([
            'child_id' => 'required|exists:children,id',
            'group_id' => 'required|exists:groups,id',
        ]);

        // Checken of de child_id wel écht een child is van de authenticated user
        $user = Auth::user();
        $child = Child::where('user_id', $user->id)->findOrFail($request->child_id);

        $subscription = DB::table('groups_has_children')
            ->where('child_id', $child->id)
            ->where(function($query) {
                $query->where('subscribed', 'accepted')->orWhere('subscribed', 'sent');
            })
            ->pluck('group_id');

        // Checken of het abonnement al bestaat
        if (in_array($request->group_id, $subscription->toArray())) return response(['Subscription already exists'], 403);

        DB::table('groups_has_children')
            ->insert([
                'child_id' => $request->child_id,
                'group_id' => $request->group_id,
                'subscribed' => 'sent',
            ]);
        return response(['message' => 'Subscription added.'], 200);
    }

    public function deleteSubscription(Request $request)
    {
        $request->validate([
            'child_id' => 'required|exists:children,id',
            'group_id' => 'required|exists:groups,id'
        ]);

        // Checken of de child_id wel écht een child is van de authenticated user
        $user = Auth::user();
        $child = Child::where('user_id', $user->id)->findOrFail($request->child_id);

        $subscription = DB::table('groups_has_children')
            ->where('child_id', $child->id)
            ->where('subscribed', 'accepted')
            ->pluck('group_id');
        // Checken of het abonnement al bestaat
        if (!in_array($request->group_id, $subscription->toArray())) return response(['Subscription already exists'], 403);

        // Verwijder de rij in de pivot tabel
        DB::table('groups_has_children')
            ->where('group_id', $request->group_id)
            ->where('child_id', $request->child_id)
            ->delete();

        return response(['message' => 'Subscription deleted'], 200);
    }

    public function getChildren()
    {
        // Mag leeg zijn, want dan heb je gewoon geen kids ;)
        $children = Child::where('user_id', Auth::user()->id)->get();
        $changedImagePath = $children->map(function ($child) {
            $child->image ?
                $child->image = rtrim(env('APP_URL'), '/') . '/storage/' . str_replace('storage/app/public', '', $child->image) :
                '';

            return $child;
        });

        return response(['data' => $children], 200);
    }

    public function addChild(Request $request)
    {
        $request->validate([
            'first_name' => 'required|max:75',
            'last_name' => 'required|max:75',
            'image' => 'image'
        ]);

        $image = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $image = $file->store('children');
        }

        $child = Child::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'image' => $request->image ? $image : null,
            'user_id' => Auth::user()->id,
        ]);

        return response(['message' => $child->name . ' is succesfully created.'], 200);
    }
}
