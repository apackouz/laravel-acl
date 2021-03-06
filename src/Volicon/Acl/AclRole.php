<?php namespace Volicon\Acl;

use Volicon\Acl\Facades\Acl as AclFacade;
use Volicon\Acl\Exceptions\NoPermissionsException;

use Volicon\Acl\Support\DataObject;
use Illuminate\Support\Collection;

/**
 * Description of Role
 *
 * @author nadav.v
 * 
 * @property int $role_id
 * @property string $name
 * @property int $type
 * @property bool $default prevent deleting the role or change its name
 * @property Collection $permissions
 * @property Collection $users users ID's
 */
class AclRole extends DataObject {

	public function __construct($role) {
		
		$this->attributes['permissions'] = new Collection();
		$this->attributes['users'] = new Collection();
		
		if($role instanceof \Volicon\Acl\Models\Role) {
			$this->attributes['role_id'] = $role->role_id;
			$this->attributes['name'] = $role->name;
			$this->attributes['type'] = $role->type;
			$this->attributes['default'] = $role->default;
			
			if(isset($role->permissions)) {
				$this->attributes['permissions'] = $this->_aclPermissions($role->permissions);
			}
			
			if(isset($role->users)) {
				$this->attributes['users'] = $this->_aclUsers($role->users);
			}
		} else if(is_array($role)) {
			$default = isset($role['default']) ? (bool)$role['default'] : FALSE;
			$this->attributes['default'] = $default;
			unset($role['default']);
			
			if(isset($role['permissions'])) {
				$this->attributes['permissions'] = $this->_aclPermissions($role['permissions']);
			
				unset($role['permissions']);
			}
			
			if(isset($role['users'])) {
				$this->attributes['users'] = $this->_aclUsers($role['users']);
				unset($role['users']);
			}
			
			foreach ($role as $key=>$value) {
				$this->attributes[$key] = $value;
			}
		}
	}
	
	public function getPermission($resource) {
		
		$permissions = $this->permissions->keyBy('resource');
		
		return isset($permissions[$resource]) ? $permissions[$resource] : new AclPermission($resource);
	}

	public function add() {
		/* @var $role_provider  RoleProviders\AclRoleProvider */
		$role_provider = AclFacade::getRoleProvider($this->attributes['type']);
		return $role_provider->addRole($this);
	}
	
	public function update() {
		/* @var $role_provider  RoleProviders\AclRoleProvider */
		$role_provider = AclFacade::getRoleProvider($this->attributes['type']);
		return $role_provider->updateRole($this);
	}
	
	public function remove() {
		
		if($this->attributes['default']) {
			throw new NoPermissionsException("You cannot remove default role.");
		}
		
		if(!isset($this->attributes['role_id']) || !$this->attributes['role_id']) {
			throw new NoPermissionsException("missing role_id");
		}
		
		/* @var $role_provider  RoleProviders\AclRoleProvider */
		$role_provider = AclFacade::getRoleProvider($this->attributes['type']);
		return $role_provider->removeRole($this->attributes['role_id']);
	}

	public function offsetSet($offset, $value) {
		
		if($offset === 'default') {
			throw new NoPermissionsException("You cannot change default field.");
		}
		
		if($offset === 'type' && isset($this->attributes['type'])) {
			throw new NoPermissionsException("You cannot change type after it set.");
		}
		
		if(isset($this->attributes['role_id']) && $offset == $this->attributes['role_id'] && $value !== $this->attributes['role_id']) {
			throw new NoPermissionsException("You cannot change id of role.");
		}
		
		if($this->attributes['default'] && $offset === 'name') {
			throw new NoPermissionsException("You cannot change default role name.");
		}
		
		if($offset === 'permissions') {
			
			$this->attributes['permissions'] = $this->_aclPermissions($value);
			
		} else if($offset === 'users') {
			
			$this->attributes['users'] = $this->_aclUsers($value);
			
		} else {
			parent::offsetSet($offset, $value);
		}
		
	}

	public function offsetUnset($offset) {
		
		if(in_array($offset, ['role_id', 'name', 'type', 'default', 'permissions', 'users'])) {
			throw new NoPermissionsException("No permission to unset: ".$offset);
		}
		
		parent::offsetUnset($offset);
		
	}

	protected function _aclPermissions($permissions) {
		
		$result = new Collection();
			
		foreach($permissions as $perm) {
			if($perm instanceof AclPermission) {
				$result[] = $perm;
			} else {
				$result[] = new AclPermission($perm);
			}
		}

		return $result;
	}

	protected function _aclUsers($users) {
		$result = new Collection();
		
		foreach($users as $user) {
			if(is_numeric($user)) {
				$result[] = (int)$user;
			} else if(is_array($user)) {
				if($user['user_id']) {
					$result[] = (int)$user['user_id'];
				}
			} else if(is_object($user)) {
				$result[] = (int)$user->user_id;
			}
		}
		
		return $result;
	}

}
