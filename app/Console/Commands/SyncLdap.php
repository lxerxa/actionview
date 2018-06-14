<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Adldap\Adldap;
use Adldap\Models\User;
use App\Acl\Eloquent\UserDirectory;

use Cartalyst\Sentinel\Users\EloquentUser;

use Sentinel;
use DB;
use Exception;

class SyncLdap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ldap:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $configs = [];

        $directories = UserDirectory::where('type', 'LDAP')
            ->where('status', 'active') 
            ->get(); 

        foreach($directories as $d)
        {
            $configs[ $d->id ] = $d->configs;
        }

        $configs = [];
        $ad = new Adldap($configs);

        foreach ($configs as $key => $config)
        {
            $provider = $ad->connect($key);

            // the user sync
            DB::table('users')->where('directory', $key)->update([ 'sync_flag' => 1 ]);

            $users = $provider->search()
                ->setDn('dc=cmri,dc=chinamobile,dc=com')
                ->where('objectClass', 'inetorgperson')
                ->get();
            foreach ($users as $user)
            {
                $dn = $user->getDn(); 
                $cn = $user->getFirstAttribute('cn'); 
                $email = $user->getFirstAttribute('mail'); 

                $eloquent_user = EloquentUser::where('directory', $key)
                    ->where('ldap_dn', $dn)
                    ->first();

                if ($eloquent_user)
                {
                    Sentinel::update($eloquent_user, [ 'first_name' => $cn, 'email' => $email, 'invalid_flag' => 0, 'sync_flag' => 0 ]);
                }
                else
                {
                    Sentinel::register([ 'directory' => $key, 'ldap_dn' => $dn, 'first_name' => $cn, 'email' => $email, 'password' => md5(rand(10000, 99999)) ], true);
                }
exit;
            }
            // disable the users
            DB::table('users')->where('directory', $key)->where('sync_flag', 1)->update([ 'sync_flag' => 0, 'invalid_flag' => 1 ]);

            // group sync
            DB::table('acl_group')->where('directory', $key)->update([ 'sync_flag' => 1 ]);

            $groups = $provider->search()
                ->setDn('dc=cmri,dc=chinamobile,dc=com')
                ->where('objectClass', 'inetorgperson')
                ->get();
            foreach ($groups as $group)
            {
                $cn = $group->getFirstAttribute('cn'); 
            }
        }
    }
}
