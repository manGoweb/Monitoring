<?php declare(strict_types = 1);

namespace Pd\Monitoring\User;

use Nextras;


/**
 * @method User getById(int $id)
 * @method User getBy(array $conds)
 */
class UsersRepository extends Nextras\Orm\Repository\Repository
{

	public static function getEntityClassNames()
	{
		return [
			User::class,
		];
	}
}
