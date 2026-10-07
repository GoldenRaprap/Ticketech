<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TicketingSeeder extends Seeder
{
    public function run(): void
    {
        $timestamp = now();

        DB::table('departments')->upsert([
            ['name' => 'College of Engineering', 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['name' => 'College of Education', 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['name' => 'College of Business', 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['name' => 'IT Services', 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['name' => 'Student Affairs', 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
        ], ['name'], ['is_active', 'updated_at']);

        DB::table('categories')->upsert([
            ['name' => 'Registrar', 'description' => 'Student records and enrollment support', 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['name' => 'Bluebook', 'description' => 'Online learning platform', 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['name' => 'PRIISM', 'description' => 'Student information portal', 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['name' => 'Network', 'description' => 'Campus network and internet', 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['name' => 'Hardware', 'description' => 'Computers and equipment', 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['name' => 'Software', 'description' => 'Applications and licensed tools', 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['name' => 'Other', 'description' => 'Other IT concerns', 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
        ], ['name'], ['description', 'is_active', 'updated_at']);
    }
}
