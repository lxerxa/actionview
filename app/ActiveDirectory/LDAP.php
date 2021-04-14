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
     * @return array 
     */
    public static function test($configs)
    {
        $ad = new Adldap(self::filterConnectConfigs($configs));

        $ret = [];
        foreach ($configs as $key => $config)
        {
            $tmp = [];
            try {
                $provider = $ad->connect($key);
            } catch (Exception $e) {
                $ret[$key] =  [ 
                    'server_connect'   => false, 
                    'user_count'       => 0, 
                    'group_count'      => 0, 
                    'group_membership' => false ];
                continue;
            }

            $tmp['server_connect'] = true;

            if (!isset($config['user_object_class']))
            {
                $tmp['user_count'] = 0;
            }
            else
            {
                $users = $provider
                    ->search()
                    ->setDn(self::getUserDn($config))
                    ->rawFilter(isset($config['user_object_filter']) ? $config['user_object_filter'] : ('(objectClass=' . $config['user_object_class'] . ')'))
                    ->where('objectClass', $config['user_object_class'])
                    ->get();
                $tmp['user_count'] = count($users);
            }

            if (!isset($config['group_object_class']))
            {
                $tmp['group_count'] = 0;
            }
            else
            {
                $groups = $provider
                    ->search()
                    ->setDn(self::getGroupDn($config))
                    ->rawFilter(isset($config['group_object_filter']) ? $config['group_object_filter'] : ('(objectClass=' . $config['group_object_class'] . ')'))
                    ->where('objectClass', $configs['default']['group_object_class'])
                    ->get();
                $tmp['group_count'] = count($groups);
            }

            if (!isset($config['group_object_class']) || !isset($config['group_membership_attr']))
            {
                $tmp['group_memebership'] = false;
            }
            else
            {
                $group = $provider
                    ->search()
                    ->setDn(self::getGroupDn($config))
                    ->where('objectClass', $config['group_object_class'])
                    ->first();
                $tmp['group_membership'] = $group ? $group->hasAttribute(strtolower($config['group_membership_attr'])) : false;
            }

            $ret[$key] = $tmp;
        }

        return $ret;
    }

    /**
     * connect ldap server.
     *
     * @var array $config
     * @return array 
     */
    public static function sync($configs)
    {
        $ret = [];
        $ad = new Adldap(self::filterConnectConfigs($configs));

        foreach ($configs as $key => $config)
        {
            $tmp = [];
            try {
                $provider = $ad->connect($key);
            } catch (Exception $e) {
                $tmp['connect'] = $tmp['user'] = $tmp['group'] = false;
                $ret[$key] = $tmp;
                continue;
            }
            $tmp['connect'] = true;
            // sync the users
            $tmp['user'] = self::syncUsers($provider, $key, $config);
            // sync the groups
            $tmp['group'] = self::syncGroups($provider, $key, $config);

            $ret[$key] = $tmp;
        }

        return $ret;
    }

    /**
     * arrange configs.
     *
     * @return array
     */
    public static function filterConnectConfigs($configs)
    {
        $connect_configs = [];
        foreach ($configs as $key => $config)
        {
            $connect = [];
            $connect['domain_controllers'] = [ $config['host'] ];
            $connect['port'] = $config['port'];
            $connect['admin_username'] = $config['admin_username'];
            $connect['admin_password'] = $config['admin_password'];
            if (isset($config['encryption']) && $config['encryption'])
            {
                if ($config['encryption'] == 'ssl')
                {
                    $connect['use_ssl'] = true;
                }
                else if ($config['encryption'] == 'tls')
                {
                    $connect['use_tls'] = true;
                }
                else
                {
                    $connect['use_ssl'] = false;
                    $connect['use_tls'] = false; 
                }
            }
            else
            {
                $connect['use_ssl'] = false;
                $connect['use_tls'] = false;   
            }
            $connect_configs[$key] = $connect;
        }

        return $connect_configs;
    }

    /**
     * get user dn.
     *
     * @return string 
     */
    public static function getUserDN($config)
    {
        $user_dn = '';
        if (isset($config['additional_user_dn']) && $config['additional_user_dn'])
        {
            if (strpos($config['additional_user_dn'], $config['base_dn']) !== false)
            {
                $user_dn = $config['additional_user_dn'];
            }
            else
            {
                $user_dn = $config['additional_user_dn'] . ',' . $config['base_dn'];
            }
        }
        else
        {
            $user_dn = $config['base_dn'];
        }
        return $user_dn;
    }

    /**
     * get group dn.
     *
     * @return string 
     */
    public static function getGroupDN($config)
    {
        $group_dn = '';
        if (isset($config['additional_group_dn']) && $config['additional_group_dn'])
        {
            if (strpos($config['additional_group_dn'], $config['base_dn']) !== false)
            {
                $group_dn = $config['additional_group_dn'];
            }
            else
            {
                $group_dn = $config['additional_group_dn'] . ',' . $config['base_dn'];
            }
        }
        else
        {
            $group_dn = $config['base_dn'];
        }
        return $group_dn;
    }

    /**
     * sync the users.
     *
     * @return bool 
     */
    public static function syncUsers($provider, $directory, $config)
    {
        if (!isset($config['user_object_class']))
        {
            return false;
        }

        // the user sync
        DB::table('users')->where('directory', $directory)->update([ 'sync_flag' => 1 ]);

        $users = $provider
            ->search()
            ->setDn(self::getUserDN($config))
            ->rawFilter(isset($config['user_object_filter']) ? $config['user_object_filter'] : ('(objectClass=' . $config['user_object_class'] . ')'))
            ->where('objectClass', $config['user_object_class'])
            ->get();
        foreach ($users as $user)
        {
            $dn = $user->getDn();
            $cn = $user->getFirstAttribute(strtolower($config['user_name_attr']));
            $email = $user->getFirstAttribute(strtolower($config['user_email_attr']));

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

        return true;
    }

    /**
     * sync the groups.
     *
     * @return bool 
     */
    public static function syncGroups($provider, $directory, $config)
    {
        if (!isset($config['group_object_class']))
        {
            return false;
        }

        $groups = $provider
            ->search()
            ->setDn(self::getGroupDN($config))
            ->rawFilter(isset($config['group_object_filter']) ? $config['group_object_filter'] : ('(objectClass=' . $config['group_object_class'] . ')'))
            ->where('objectClass', $config['group_object_class'])
            ->get();

        foreach ($groups as $group)
        {
            $dn = $group->getDn();
            $cn = $group->getFirstAttribute(strtolower($config['group_name_attr']));
            $members = $group->getAttribute(strtolower($config['group_membership_attr']));

            $users = EloquentUser::where('directory', $directory)
                ->whereIn('ldap_dn', $members)
                ->get([ 'name' ])
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
                Group::create([ 
                    'name' => $cn, 
                    'users' => $user_ids, 
                    'ldap_dn' => $dn, 
                    'directory' => $directory ]);
            }
        }

        return true;
    }

    /**
     * ldap user authenticate.
     *
     * @var array $configs
     * @var string $username
     * @var string $password
     * @return object 
     */
    public static function attempt($configs, $username, $password)
    {
        $pass = false;
        $user = null;

        $ad = new Adldap(self::filterConnectConfigs($configs));

        foreach ($configs as $key => $config)
        {
            try {
                $provider = $ad->connect($key);

                $user = EloquentUser::where('directory', $key)
                    ->where('email', $username)
                    ->first(); 

                if (!$user || !$user->ldap_dn)
                {
                    continue;
                }

                if ($provider->auth()->attempt($user->ldap_dn, $password))
                {
                    $pass = true;
                    break;
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return $pass ? $user : null;
    }
}
