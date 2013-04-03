<?php
/**
 * WithRelatedBehavior class file.
 *
 * @author Alexander Kochetov <creocoder@gmail.com>
 * @link https://github.com/yiiext/with-related-behavior
 */

/**
 * Allows you to save related models along with the main model.
 * All relation types are supported.
 *
 * @property CActiveRecord $owner
 * @method CActiveRecord getOwner()
 *
 * @version 0.65
 * @package yiiext.with-related-behavior
 */
class WithRelatedBehavior extends CActiveRecordBehavior
{
	/**
	 * Validate main model and all it's related models recursively.
	 * @param array $data attributes and relations.
	 * @param boolean $clearErrors whether to call {@link CModel::clearErrors} before performing validation.
	 * @param CActiveRecord $owner for internal needs.
	 * @return boolean whether the validation is successful without any error.
	 */
	public function validate($data=null,$clearErrors=true,$owner=null)
	{
		if($owner===null)
			$owner=$this->getOwner();

		if($data===null)
		{
			$attributes=null;
			$newData=array();
		}
		else
		{
			// retrieve real class attributes that was specified in the class declaration
			$classAttributes=get_class_vars(get_class($owner));
			unset($classAttributes['db']); // has nothing in common with the application logic
			$classAttributes=array_keys($classAttributes);

			// mixing virtual attributes that represents database table columns with real class attributes
			$attributeNames=array_merge($classAttributes,$owner->attributeNames());
			// array_intersect must not be used here because when error_reporting is -1 notice will happen
			// since $data array contains not just scalar string values
			$attributes=array_uintersect($data,$attributeNames,
				create_function('$x,$y','return !is_string($x) || !is_string($y) ? -1 : strcmp($x,$y);'));

			if($attributes===array())
				$attributes=null;

			// array_udiff must not be used here because when error_reporting is -1 notice will happen
			// since $data array contains not just scalar string values
			$newData=array_udiff($data,$attributeNames,
				create_function('$x,$y','return !is_string($x) || !is_string($y) ? -1 : strcmp($x,$y);'));
		}

		$valid=$owner->validate($attributes,$clearErrors);

		foreach($newData as $name=>$data)
		{
			if(!is_array($data))
				$name=$data;

			if(!$owner->hasRelated($name))
				continue;

			/** @var CActiveRecord|CActiveRecord[] $related */
			$related=$owner->getRelated($name);

			if(is_array($related))
			{
				foreach($related as $model)
				{
					if(is_array($data))
						$valid=$this->validate($data,$clearErrors,$model) && $valid;
					else
						$valid=$model->validate(null,$clearErrors) && $valid;
				}
			}
			else
			{
				if(is_array($data))
					$valid=$this->validate($data,$clearErrors,$related) && $valid;
				else
					$valid=$related->validate(null,$clearErrors) && $valid;
			}
		}

		return $valid;
	}

	/**
	 * @param array|string $foreignKey
	 * @param CDbTableSchema $ownerTableSchema
	 * @param CDbTableSchema $dependentTableSchema
	 * @return array
	 */
	protected function getDependencyAttributes($foreignKey, $ownerTableSchema, $dependentTableSchema)
	{
		$dbSchema = $this->getOwner()->getDbConnection()->getSchema();
		$map = array();

		if(is_string($foreignKey))
			$foreignKey=preg_split('/\s*,\s*/',$foreignKey,-1,PREG_SPLIT_NO_EMPTY);

		foreach($foreignKey as $fk=>$pk)
		{
			if(is_int($fk))
			{
				$index = $fk;
				$fk = $pk;

				if(isset($ownerTableSchema->foreignKeys[$fk]) && $dbSchema->compareTableNames($dependentTableSchema->rawName,$ownerTableSchema->foreignKeys[$fk][0]))
					$pk=$ownerTableSchema->foreignKeys[$fk][1];
				else // FK constraints undefined
				{
					if(is_array($dependentTableSchema->primaryKey)) // composite PK
						$pk=$dependentTableSchema->primaryKey[$index];
					else
						$pk=$dependentTableSchema->primaryKey;
				}
			}
			$map[$fk] = $pk;
		}
		return $map;
	}

	/**
	 * Save main model and all it's related models recursively.
	 * @param bool $runValidation whether to perform validation before saving the record.
	 * @param array $data attributes and relations.
	 * @param CActiveRecord $owner for internal needs.
	 * @return boolean whether the saving succeeds.
	 * @throws CDbException
	 * @throws Exception
	 */
	public function save($runValidation=true,$data=null,$owner=null)
	{
		if($owner===null)
		{
			if($runValidation && !$this->validate($data))
				return false;

			$owner=$this->getOwner();
		}

		/** @var CDbConnection $db */
		$db=$owner->getDbConnection();

		if($db->getCurrentTransaction()===null)
			$transaction=$db->beginTransaction();

		try
		{
			if($data===null)
			{
				$attributes=null;
				$newData=array();
			}
			else
			{
				// not mixing virtual attributes that represents database table columns with real class attributes
				// since real class attributes shouldn't be persisted in the database, it's actual only for validation part
				$attributeNames=$owner->attributeNames();
				// array_intersect must not be used here because when error_reporting is -1 notice will happen
				// since $data array contains not just scalar string values
				$attributes=array_uintersect($data,$attributeNames,
					create_function('$x,$y','return !is_string($x) || !is_string($y) ? -1 : strcmp($x,$y);'));

				if($attributes===array())
					$attributes=null;

				// array_diff must not be used here because when error_reporting is -1 notice will happen
				// since $data array contains not just scalar string values
				$newData=array_udiff($data,$attributeNames,
					create_function('$x,$y','return !is_string($x) || !is_string($y) ? -1 : strcmp($x,$y);'));
			}

			$ownerTableSchema=$owner->getTableSchema();
			/** @var CDbCommandBuilder $builder */
			$builder=$owner->getCommandBuilder();
			$schema=$builder->getSchema();
			$relations=$owner->getMetaData()->relations;
			$queue=array();

			foreach($newData as $name=>$data)
			{
				if(!is_array($data))
				{
					$name=$data;
					$data=null;
				}

				if(!$owner->hasRelated($name))
					continue;

				$relationClass=get_class($relations[$name]);
				$relatedClass=$relations[$name]->className;

				if($relationClass===CActiveRecord::BELONGS_TO)
				{
					/** @var CActiveRecord|CActiveRecord[] $related */
					$related=$owner->getRelated($name);
					$relatedTableSchema=$related->getTableSchema();

					if($data!==null)
						$this->save(false,$data,$related);
					else
						$related->getIsNewRecord() ? $related->insert() : $related->update();

					$fks = $relations[$name]->foreignKey;
					$map = $this->getDependencyAttributes($fks, $relatedTableSchema, $ownerTableSchema);
					foreach ($map as $fk => $pk) {
						$owner->$fk = $related->$pk;
					}
				}
				else
					$queue[]=array($relationClass,$relatedClass,$relations[$name]->foreignKey,$name,$data);
			}

			if(!($owner->getIsNewRecord() ? $owner->insert($attributes) : $owner->update($attributes)))
				return false;

			foreach($queue as $pack)
			{
				list($relationClass,$relatedClass,$fks,$name,$data)=$pack;
				$related=$owner->getRelated($name);
				$relatedTableSchema=CActiveRecord::model($relatedClass)->getTableSchema();

				switch($relationClass)
				{
					case CActiveRecord::HAS_ONE:
						$map = $this->getDependencyAttributes($fks, $ownerTableSchema, $relatedTableSchema);
						foreach ($map as $fk => $pk) {
							$related->$fk = $owner->$pk;
						}

						if($data===null)
							$related->getIsNewRecord() ? $related->insert() : $related->update();
						else
							$this->save(false,$data,$related);
						break;
					case CActiveRecord::HAS_MANY:
						$map = $this->getDependencyAttributes($fks, $ownerTableSchema, $relatedTableSchema);
						foreach($related as $model)
						{
							foreach($map as $fk=>$pk)
								$model->$fk = $owner->$pk;

							if($data===null)
								$model->getIsNewRecord() ? $model->insert() : $model->update();
							else
								$this->save(false,$data,$model);
						}
						break;
					case CActiveRecord::MANY_MANY:
						if(!preg_match('/^\s*(.*?)\((.*)\)\s*$/',$fks,$matches))
							throw new CDbException(Yii::t('yiiext','The relation "{relation}" in active record class "{class}" is specified with an invalid foreign key. The format of the foreign key must be "joinTable(fk1,fk2,...)".',
								array('{class}'=>get_class($owner),'{relation}'=>$name)));

						if(($joinTable=$schema->getTable($matches[1]))===null)
							throw new CDbException(Yii::t('yiiext','The relation "{relation}" in active record class "{class}" is not specified correctly: the join table "{joinTable}" given in the foreign key cannot be found in the database.',
								array('{class}'=>get_class($owner),'{relation}'=>$name,'{joinTable}'=>$matches[1])));

						$fks=preg_split('/\s*,\s*/',$matches[2],-1,PREG_SPLIT_NO_EMPTY);
						$ownerMap=array();
						$relatedMap=array();
						$fkDefined=true;

						foreach($fks as $fk)
						{
							if(!isset($joinTable->columns[$fk]))
								throw new CDbException(Yii::t('yii','The relation "{relation}" in active record class "{class}" is specified with an invalid foreign key "{key}". There is no such column in the table "{table}".',
									array('{class}'=>get_class($owner),'{relation}'=>$name,'{key}'=>$fk,'{table}'=>$joinTable->name)));

							if(isset($joinTable->foreignKeys[$fk]))
							{
								list($tableName,$pk)=$joinTable->foreignKeys[$fk];

								if(!isset($ownerMap[$pk]) && $schema->compareTableNames($ownerTableSchema->rawName,$tableName))
									$ownerMap[$pk]=$fk;
								else if(!isset($relatedMap[$pk]) && $schema->compareTableNames($relatedTableSchema->rawName,$tableName))
									$relatedMap[$pk]=$fk;
								else
								{
									$fkDefined=false;
									break;
								}
							}
							else
							{
								$fkDefined=false;
								break;
							}
						}

						if(!$fkDefined)
						{
							$ownerMap=array();
							$relatedMap=array();

							foreach($fks as $i=>$fk)
							{
								if($i<count($ownerTableSchema->primaryKey))
								{
									$pk=is_array($ownerTableSchema->primaryKey) ? $ownerTableSchema->primaryKey[$i] : $ownerTableSchema->primaryKey;
									$ownerMap[$pk]=$fk;
								}
								else
								{
									$j=$i-count($ownerTableSchema->primaryKey);
									$pk=is_array($relatedTableSchema->primaryKey) ? $relatedTableSchema->primaryKey[$j] : $relatedTableSchema->primaryKey;
									$relatedMap[$pk]=$fk;
								}
							}
						}

						if($ownerMap===array() && $relatedMap===array())
							throw new CDbException(Yii::t('yii','The relation "{relation}" in active record class "{class}" is specified with an incomplete foreign key. The foreign key must consist of columns referencing both joining tables.',
								array('{class}'=>get_class($owner),'{relation}'=>$name)));

						$insertAttributes=array();
						$deleteAttributes=array();

						foreach($related as $model)
						{
							$newFlag=$model->getIsNewRecord();

							if($data===null)
								$newFlag ? $model->insert() : $model->update();
							else
								$this->save(false,$data,$model);

							$joinTableAttributes=array();

							foreach($ownerMap as $pk=>$fk)
								$joinTableAttributes[$fk]=$owner->$pk;

							foreach($relatedMap as $pk=>$fk)
								$joinTableAttributes[$fk]=$model->$pk;

							if(!$newFlag)
								$deleteAttributes[]=$joinTableAttributes;

							$insertAttributes[]=$joinTableAttributes;
						}

						if($deleteAttributes!==array())
						{
							$condition=$builder->createInCondition($joinTable,array_merge(array_values($ownerMap),array_values($relatedMap)),$deleteAttributes);
							$criteria=$builder->createCriteria($condition);
							$builder->createDeleteCommand($joinTable,$criteria)->execute();
						}

						foreach($insertAttributes as $attributes)
							$builder->createInsertCommand($joinTable,$attributes)->execute();
						break;
				}
			}

			if(isset($transaction))
				$transaction->commit();

			return true;
		}
		catch(Exception $e)
		{
			if(isset($transaction))
				$transaction->rollback();

			throw $e;
		}
	}

	/**
	 * @param string $name
	 * @param mixed $keys
	 */
	public function link($name,$keys)
	{
		$owner=$this->getOwner();

		if(!$owner->getMetaData()->hasRelation($name))
			throw new CDbException(Yii::t('yiiext','The relation "{relation}" in active record class "{class}" is not specified.',
				array('{class}'=>get_class($owner),'{relation}'=>$name)));

		$ownerTableSchema=$owner->getTableSchema();
		$builder=$owner->getCommandBuilder();
		$schema=$builder->getSchema();
		$relation=$owner->getMetaData()->relations[$name];
		$relationClass=get_class($relation);
		$relatedClass=$relation->className;

		switch($relationClass)
		{
			case CActiveRecord::BELONGS_TO:
				break;
			case CActiveRecord::HAS_ONE:
				break;
			case CActiveRecord::HAS_MANY:
				break;
			case CActiveRecord::MANY_MANY:
				break;
		}
	}

	/**
	 * @param string $name
	 * @param mixed $keys
	 */
	public function unlink($name,$keys=null)
	{
		$owner=$this->getOwner();

		if(!$owner->getMetaData()->hasRelation($name))
			throw new CDbException(Yii::t('yiiext','The relation "{relation}" in active record class "{class}" is not specified.',
				array('{class}'=>get_class($owner),'{relation}'=>$name)));

		$ownerTableSchema=$owner->getTableSchema();
		$builder=$owner->getCommandBuilder();
		$schema=$builder->getSchema();
		$relation=$owner->getMetaData()->relations[$name];
		$relationClass=get_class($relation);
		$relatedClass=$relation->className;

		switch($relationClass)
		{
			case CActiveRecord::BELONGS_TO:
				break;
			case CActiveRecord::HAS_ONE:
				break;
			case CActiveRecord::HAS_MANY:
				break;
			case CActiveRecord::MANY_MANY:
				break;
		}
	}
}