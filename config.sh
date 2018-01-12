#!/bin/sh

echo "configure starting..."

chmod -R 777 storage
chmod -R 777 bootstrap/cache

#replace eloquent model
export eloquentuserfile=vendor/cartalyst/sentinel/src/Users/EloquentUser.php
export eloquentactivationfile=vendor/cartalyst/sentinel/src/Activations/EloquentActivation.php
export eloquentpersistencefile=vendor/cartalyst/sentinel/src/Persistences/EloquentPersistence.php 
export eloquentthrottlefile=vendor/cartalyst/sentinel/src/Throttling/EloquentThrottle.php 
export eloquentrolefile=vendor/cartalyst/sentinel/src/Roles/EloquentRole.php 
export eloquentreminderfile=vendor/cartalyst/sentinel/src/Reminders/EloquentReminder.php 

sed -i 's/Illuminate\\Database\\Eloquent\\Model/Jenssegers\\Mongodb\\Eloquent\\Model/g' $eloquentuserfile 
sed -i 's/Illuminate\\Database\\Eloquent\\Model/Jenssegers\\Mongodb\\Eloquent\\Model/g' $eloquentactivationfile 
sed -i 's/Illuminate\\Database\\Eloquent\\Model/Jenssegers\\Mongodb\\Eloquent\\Model/g' $eloquentpersistencefile 
sed -i 's/Illuminate\\Database\\Eloquent\\Model/Jenssegers\\Mongodb\\Eloquent\\Model/g' $eloquentthrottlefile 
sed -i 's/Illuminate\\Database\\Eloquent\\Model/Jenssegers\\Mongodb\\Eloquent\\Model/g' $eloquentrolefile 
sed -i 's/Illuminate\\Database\\Eloquent\\Model/Jenssegers\\Mongodb\\Eloquent\\Model/g' $eloquentreminderfile 

#initialize activition's completed
export activationrepofile=vendor/cartalyst/sentinel/src/Activations/IlluminateActivationRepository.php
if [ `grep -c '$activation->completed = false;' $activationrepofile` -eq 0 ]; then
  sed -i '/$activation->user_id = $user->getUserId();/a\        $activation->completed = false;' $activationrepofile
fi
 
#add avatar field to fillable
if [ `grep -c "'avatar'," $eloquentuserfile` -eq 0 ]; then
  sed -i "/fillable/a\        'avatar'," $eloquentuserfile 
fi

echo "configure complete."
