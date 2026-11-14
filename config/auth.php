<?php
return [
 'defaults'=>['guard'=>'web','passwords'=>'users'],
 'guards'=>['web'=>['driver'=>'session','provider'=>'users']],
 'providers'=>['users'=>['driver'=>'eloquent','model'=>App\Models\User::class]],
 'passwords'=>['users'=>['provider'=>'users','table'=>'password_reset_tokens','expire'=>60,'throttle'=>60]],
 'password_timeout'=>10800,
 'demo_login'=>[
  'enabled'=>(bool)env('DEMO_LOGIN_SHORTCUTS', false) && (bool)env('DEMO_SEED', false),
  'password'=>env('DEMO_PASSWORD'),
  'owner_email'=>env('OWNER_EMAIL', 'admin@skillmatching.local'),
  'owner_password'=>env('OWNER_PASSWORD'),
 ],
];
