<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as FakerFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = FakerFactory::create();
        $faker->seed(666);
        $dt = $faker->dateTimeThisYear()->format('Y-m-d H:i:s');

        for ($i = 1; $i < 6; $i++) {
            DB::table('users')->insert([
                'id' => $i,
                'first_name' => $faker->firstName(),
                'last_name' => $faker->lastName(),
                'email' => $faker->email(),
                'email_verified_at' => rand(0, 1) == 1 ? $faker->dateTimeBetween('2023-01-01', 'now') : null,
                'password' => Hash::make('Azerty123'),
                'role' => $i < 4 ? 'user' : 'organisation',
                'profile_picture' => $i > 2 ? null : $faker->image(storage_path('app/public/users/profile_pictures'), 640, 480, 'children'),
                'banner_picture' => $i > 2 && $i < 5 ? $faker->image(storage_path('app/public/users/banner_pictures'), 640, 480, 'children') : null,
                'status' => 'off',
                'residence_id' => $i,
            ]);
        }
    }
}
