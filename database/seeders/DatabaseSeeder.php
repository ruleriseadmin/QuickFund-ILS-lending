<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        /**
         * Create the quickfund user
         */
        User::create([
            'id' => User::APPLICATION_ID,
            'name' => 'Quickfund',
            'email' => config('quickfund.username'),
            'password' => Hash::make(config('quickfund.password'))
        ]);

        /**
         * Create the Interswitch user
         */
        User::create([
            'id' => User::INTERSWITCH_ID,
            'name' => 'Interswitch',
            'email' => 'lending_quick@quickfundmfb.com',
            'password' => Hash::make('quick@lending_2018')
        ]);

        $this->call([
            OfferSeeder::class,
            FeeSeeder::class
        ]);
    }
}
