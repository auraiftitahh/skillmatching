<?php
use Illuminate\Support\Facades\Schedule;
Schedule::command('vacancies:expire')->hourly();
