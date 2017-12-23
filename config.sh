#!/bin/sh

echo "configure starting..."

chmod -R 777 storage
chmod -R 777 bootstrap/cache

#replace eloquent model
sed -i 's/Illuminate\\Database\\Eloquent\\Model/Jenssegers\\Mongodb\\Eloquent\\Model/g' vendor/cartalyst/sentinel/src/Users/EloquentUser.php
#replace eloquent model
sed -i 's/Illuminate\\Database\\Eloquent\\Model/Jenssegers\\Mongodb\\Eloquent\\Model/g' vendor/cartalyst/sentinel/src/Activations/EloquentActivation.php 
#replace eloquent model
sed -i 's/Illuminate\\Database\\Eloquent\\Model/Jenssegers\\Mongodb\\Eloquent\\Model/g' vendor/cartalyst/sentinel/src/Persistences/EloquentPersistence.php
#initialize activition's completed
sed -i "/activation->save/i\        \$activation->completed = false;" vendor/cartalyst/sentinel/src/Activations/IlluminateActivationRepository.php 
#add avatar field to fillable
sed -i "/fillable/a\        'avatar'," vendor/cartalyst/sentinel/src/Users/EloquentUser.php

echo "configure complete."
