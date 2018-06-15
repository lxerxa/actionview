<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Adldap\Adldap;
use Adldap\Models\User;

use App\Acl\Eloquent\Group;
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

        $configs = [
            'cmri' => [
                'domain_controllers' => [ '10.1.5.179' ],
                'port'               => 389,
                'base_dn'            => 'dc=chinamobile,dc=com',
                'admin_username'     => 'cn=admin,dc=chinamobile,dc=com',
                'admin_password'     => 'chinamobile',
            ]
        ];
        $ad = new Adldap($configs);

        foreach ($configs as $key => $config)
        {
            $provider = $ad->connect($key);
            // sync the users
            // $this->syncUsers($provider, $key, $config);
            // sync the groups
            $this->syncGroups($provider, $key, $config);
        }
    }

    /**
     * sync the users.
     *
     * @return void
     */
    public function syncUsers($provider, $directory, $config)
    {
        // the user sync
        DB::table('users')->where('directory', $directory)->update([ 'sync_flag' => 1 ]);

        $users = $provider->search()
            ->setDn('dc=cmri,dc=chinamobile,dc=com')
            ->where('objectClass', 'inetorgperson')
            ->get();
        foreach ($users as $user)
        {
            $dn = $user->getDn();
            $cn = $user->getFirstAttribute('cn');
            $email = $user->getFirstAttribute('mail');

            $eloquent_user = EloquentUser::where('directory', $directory)
                ->where('ldap_dn', $dn)
                ->first();
            if ($eloquent_user)
            {
                Sentinel::update($eloquent_user, [
                    'first_name' => $cn,
                    'email' => $email,
                    'invalid_flag' => 0,
                     'sync_flag' => 0 ]);
            }
            else
            {
                Sentinel::register([
                    'directory' => $directory,
                    'ldap_dn' => $dn,
                    'first_name' => $cn,
                    'email' => $email,
                    'password' => md5(rand(10000, 99999)) ],
                    true);
            }
        }
        // disable the users
        DB::table('users')
            ->where('directory', $directory)
            ->where('sync_flag', 1)
            ->update([ 'sync_flag' => 0, 'invalid_flag' => 1 ]);
    }

    /**
     * sync the groups.
     *
     * @return void
     */
    public function syncGroups($provider, $directory, $config)
    {
        // the user sync
        DB::table('acl_group')
            ->where('directory', $directory)
            ->update([ 'sync_flag' => 1 ]);

        $groups = $provider
            ->search()
            ->setDn('dc=cmri,dc=chinamobile,dc=com')
            ->where('objectClass', 'groupOfUniqueNames')
            ->get();

        foreach ($groups as $group)
        {
            $dn = $group->getDn();
            $cn = $group->getFirstAttribute('cn');
            $members = $group->getAttribute('uniquemember');

            $users = EloquentUser::where('directory', $directory)
                ->whereIn('ldap_dn', $members)
                ->get([ 'first_name' ])
                ->toArray();

            $user_ids = [];
            if ($users)
            {
                $user_ids = array_column($users, '_id');
            }

            $eloquent_group = Group::where('directory', $directory)
                ->where('ldap_dn', $dn)
                ->first();
            if ($eloquent_group)
            {
                $eloquent_group
                    ->fill([ 'first_name' => $cn, 'users' => $user_ids, 'invalid_flag' => 0 ])
                    ->save();
            }
            else
            {
                Group::create([ 'name' => $cn, 'users' => $user_ids, 'ldap_dn' => $dn, 'directory' => $directory ]);
            }
        }
        // disable the users
        DB::table('acl_group')
            ->where('directory', $directory)
            ->where('sync_flag', 1)
            ->update([ 'sync_flag' => 0, 'invalid_flag' => 1 ]);
    }
}
