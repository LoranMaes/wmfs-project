<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as FakerFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
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

        // $child_id = rand(1, 9);
        // $group_id = rand(1, 2);
        for ($i = 1; $i < 6; $i++) {
            $rand = rand(0, 1);
            DB::table('notifications')->insert([
                'id' => $i,
                'message' => $faker->text(100),
                'deadline' => $rand == 1 ? $faker->dateTimeBetween('now', '+4 weeks') : null,
                'duration' => $rand !=  1 ? $faker->time('H:i:s') : null,
                'image' => $faker->image(storage_path('app/public/notifications'), 640, 480, 'activities'),
                'type' => $rand == 1 ? 'todo' : 'information',
                'event' => rand(0, 1),
                'obligatory' => $rand,
                'group_id' => rand(1, 7)
            ]);
        }

        for ($i = 1; $i < 6; $i++) {
            for ($j = 1; $j  < 10; $j++) {
                DB::table('notifications_has_children')->insert([
                    'notification_id' => $i,
                    'child_id' => $j,
                    'seen' => rand(0, 1),
                    'filled_in' => rand(0, 1),
                ]);
            }
        }
    }
}
