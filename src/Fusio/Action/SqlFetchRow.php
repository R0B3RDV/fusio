<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 * 
 * Copyright (C) 2015 Christoph Kappestein <k42b3.x@gmail.com>
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Fusio\Action;

use Doctrine\DBAL\Connection;
use Fusio\ActionInterface;
use Fusio\ConfigurationException;
use Fusio\Parameters;
use Fusio\Response;
use Fusio\Body;
use Fusio\Form;
use Fusio\Form\Element;
use PSX\Http\Exception as StatusCode;
use PSX\Util\CurveArray;

/**
 * SqlFetchRow
 *
 * @author  Christoph Kappestein <k42b3.x@gmail.com>
 * @license http://www.gnu.org/licenses/gpl-3.0
 * @link    http://fusio-project.org
 */
class SqlFetchRow implements ActionInterface
{
	/**
	 * @Inject
	 * @var Doctrine\DBAL\Connection
	 */
	protected $connection;

	/**
	 * @Inject
	 * @var Fusio\Connector
	 */
	protected $connector;

	public function getName()
	{
		return 'SQL-Fetch-Row';
	}

	public function handle(Parameters $parameters, Body $data, Parameters $configuration)
	{
		$connection = $this->connector->getConnection($configuration->get('connection'));

		if($connection instanceof Connection)
		{
			$sql = $configuration->get('sql');

			preg_match_all('/(\:)([A-z0-9\-\_\/]+)/', $sql, $matches);

			$types  = isset($matches[1]) ? $matches[1] : array();
			$keys   = isset($matches[2]) ? $matches[2] : array();
			$params = array();

			foreach($keys as $index => $key)
			{
				$sql   = str_replace($types[$index] . $key, '?', $sql);
				$value = null;

				if($types[$index] == ':')
				{
					$value = $parameters->get($key) ?: null;
				}

				if($value instanceof RecordInterface || $value instanceof \stdClass || is_array($value))
				{
					$value = serialize($value);
				}

				$params[$index] = $value;
			}

			$result = $connection->fetchAssoc($sql, $params);

			if(empty($result))
			{
				throw new StatusCode\NotFoundException('Entry not available');
			}

			return new Response(200, [], CurveArray::nest($result));
		}
		else
		{
			throw new ConfigurationException('Given connection must be an DBAL connection');
		}
	}

	public function getForm()
	{
		$form = new Form\Container();
		$form->add(new Element\Connection('connection', 'Connection', $this->connection));
		$form->add(new Element\TextArea('sql', 'SQL', 'sql', 'The SELECT statment which gets executed. Uri fragments and GET parameters can be used with i.e. <code>:news_id</code>'));

		return $form;
	}
}