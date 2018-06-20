<?php
namespace App\ActiveDirectory;

use Adldap\Adldap;

use Cartalyst\Sentinel\Users\EloquentUser;
use App\Acl\Eloquent\Group;

use Sentinel;
use DB;
use Exception;

class LDAP {

    /**
     * connect ldap server.
     *
     * @var array $config
     * @return provider
     */
    public static function test($configs)
    {

        $configs = [
            'cmri' => [
                'domain_controllers' => [ '10.1.5.179' ],
                'port'               => 3892,
                'base_dn'            => 'dc=chinamobile,dc=com',
                'admin_username'     => 'cn=admin,dc=chinamobile,dc=com',
                'admin_password'     => 'chinamobile',
            ]
        ];

        $ad = new Adldap($configs);

        foreach ($configs as $key => $config)
        {
            try {
              $provider = $ad->connect($key);
            } catch (Exception $e) {
              echo 'aa';exit;
            }
        }
    }

    /**
     * connect ldap server.
     *
     * @var array $config
     * @return provider 
     */
    public static function sync($configs)
    {
        $ad = new Adldap($configs);

        foreach ($configs as $key => $config)
        {
            $provider = $ad->connect($key);
            // sync the users
            self::syncUsers($provider, $key, $config);
            // sync the groups
            self::syncGroups($provider, $key, $config);
        }
    }

    /**
     * arrange configs.
     *
     * @return array
     */
    public static function arrangeConfigs($configs)
    {
        foreach ($configs as $key => $config)
        {
            if (isset($config['encryption']) && $config['encryption'])
            {
                if ($config['encryption'] == 'ssl')
                {
                    $configs[$key]['use_ssl'] = true;
                }
                else if ($config['encryption'] == 'tls')
                {
                    $configs[$key]['use_tls'] = true;
                }
                else
                {
                    $configs[$key]['use_ssl'] = false;
                    $configs[$key]['use_tls'] = false; 
                }
            }
            else
            {
                $configs[$key]['use_ssl'] = false;
                $configs[$key]['use_tls'] = false;   
            }

            // user dn
            if (isset($config['additional_user_dn']) && $config['additional_user_dn'])
            {
                if (strpos($config['additional_user_dn'], $config['base_dn']) !== false)
                {
                    $configs[$key]['user_dn'] = $config['additional_user_dn'];
                }
                else
                {
                    $configs[$key]['user_dn'] = $config['additional_user_dn'] . ',' . $config['base_dn'];
                }
            }
            else
            {
                $configs[$key]['user_dn'] = $config['base_dn'];
            }

            // group dn
            if (isset($config['additional_group_dn']) && $config['additional_group_dn'])
            {
                if (strpos($config['additional_group_dn'], $config['base_dn']) !== false)
                {
                    $configs[$key]['group_dn'] = $config['additional_group_dn'];
                }
                else
                {
                    $configs[$key]['group_dn'] = $config['additional_group_dn'] . ',' . $config['base_dn'];
                }
            }
            else
            {
                $configs[$key]['group_dn'] = $config['base_dn'];
            }
        }

        return $configs;
    }

    /**
     * sync the users.
     *
     * @return void
     */
    public static function syncUsers($provider, $directory, $config)
    {
        // the user sync
        DB::table('users')->where('directory', $directory)->update([ 'sync_flag' => 1 ]);

        $users = $provider
            ->search()
            ->setDn($config['user_dn'])
            ->where('objectClass', $config['user_object_class'])
            ->get();
        foreach ($users as $user)
        {
            $dn = $user->getDn();
            $cn = $user->getFirstAttribute($config['user_name_attr']);
            $email = $user->getFirstAttribute($config['user_email_attr']);

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
    public static function syncGroups($provider, $directory, $config)
    {
        $groups = $provider
            ->search()
            ->setDn($config['group_dn'])
            ->where('objectClass', $config['group_object_class'])
            ->get();

        foreach ($groups as $group)
        {
            $dn = $group->getDn();
            $cn = $group->getFirstAttribute($config['group_name_attr']);
            $members = $group->getAttribute($config['group_membership_attr']);

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
                    ->fill([ 'name' => $cn, 'users' => $user_ids ])
                    ->save();
            }
            else
            {
                Group::create([ 'name' => $cn, 'users' => $user_ids, 'ldap_dn' => $dn, 'directory' => $directory ]);
            }
        }
    }
}
