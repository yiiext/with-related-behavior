<?php
/**
 * WithRelatedBehavior class file.
 *
 * @author Alexander Kochetov <creocoder@gmail.com>
 * @link http://code.google.com/p/yiiext/
 */

/**
 * Allows to save related models with the main model.
 * All relation types supported.
 *
 * @version 0.52
 * @package yiiext.behaviors.model.wr
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
			$attributeNames=$owner->attributeNames();
			$attributes=array_intersect($data,$attributeNames);

			if($attributes===array())
				$attributes=null;

			$newData=array_diff($data,$attributeNames);
		}

		$valid=$owner->validate($attributes,$clearErrors);

		foreach($newData as $name=>$data)
		{
			if(!is_array($data))
				$name=$data;

			if(!$owner->hasRelated($name))
				continue;

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
	 * Insert main model and all it's related models recursively.
	 * @param array $data attributes and relations.
	 * @param CActiveRecord $owner for internal needs.
	 * @return boolean whether the record is inserted successfully.
	 */
	public function insert($data=null,$owner=null)
	{
		if($owner===null)
			$owner=$this->getOwner();

		$db=$owner->getDbConnection();
		$extTransFlag=$db->getCurrentTransaction();

		if($extTransFlag===null)
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
				$attributeNames=$owner->attributeNames();
				$attributes=array_intersect($data,$attributeNames);

				if($attributes===array())
					$attributes=null;

				$newData=array_diff($data,$attributeNames);
			}

			$ownerTableSchema=$owner->getTableSchema();
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
					$related=$owner->getRelated($name);

					if($data!==null)
						$this->insert($data,$related);
					else if($related->getIsNewRecord())
						$related->insert();

					$relatedTableSchema=CActiveRecord::model($relatedClass)->getTableSchema();
					$fks=preg_split('/\s*,\s*/',$relations[$name]->foreignKey,-1,PREG_SPLIT_NO_EMPTY);

					foreach($fks as $i=>$fk)
					{
						if(!isset($ownerTableSchema->columns[$fk]))
							throw new CDbException(Yii::t('yiiext','The relation "{relation}" in active record class "{class}" is specified with an invalid foreign key "{key}". There is no such column in the table "{table}".',
								array('{class}'=>get_class($owner),'{relation}'=>$relations[$name],'{key}'=>$fk,'{table}'=>$ownerTableSchema->name)));

						if(isset($ownerTableSchema->foreignKeys[$fk]) && $schema->compareTableNames($relatedTableSchema->rawName,$ownerTableSchema->foreignKeys[$fk][0]))
							$pk=$ownerTableSchema->foreignKeys[$fk][1];
						else // FK constraints undefined
						{
							if(is_array($relatedTableSchema->primaryKey)) // composite PK
								$pk=$relatedTableSchema->primaryKey[$i];
							else
								$pk=$relatedTableSchema->primaryKey;
						}

						$owner->$fk=$related->$pk;
					}
				}
				else
					$queue[]=array($relationClass,$relatedClass,$relations[$name]->foreignKey,$name,$data);
			}

			if($owner->getIsNewRecord() && !$owner->insert($attributes))
				return false;

			foreach($queue as $pack)
			{
				list($relationClass,$relatedClass,$foreignKey,$name,$data)=$pack;
				$related=$owner->getRelated($name);

				switch($relationClass)
				{
					case CActiveRecord::HAS_ONE:
						$relatedTableSchema=CActiveRecord::model($relatedClass)->getTableSchema();
						$fks=preg_split('/\s*,\s*/',$foreignKey,-1,PREG_SPLIT_NO_EMPTY);

						foreach($fks as $i=>$fk)
						{
							if(!isset($relatedTableSchema->columns[$fk]))
								throw new CDbException(Yii::t('yiiext','The relation "{relation}" in active record class "{class}" is specified with an invalid foreign key "{key}". There is no such column in the table "{table}".',
									array('{class}'=>get_class($owner),'{relation}'=>$name,'{key}'=>$fk,'{table}'=>$relatedTableSchema->name)));

							if(isset($relatedTableSchema->foreignKeys[$fk]) && $schema->compareTableNames($ownerTableSchema->rawName,$relatedTableSchema->foreignKeys[$fk][0]))
								$pk=$relatedTableSchema->foreignKeys[$fk][1];
							else // FK constraints undefined
							{
								if(is_array($ownerTableSchema->primaryKey)) // composite PK
									$pk=$ownerTableSchema->primaryKey[$i];
								else
									$pk=$ownerTableSchema->primaryKey;
							}

							$related->$fk=$owner->$pk;
						}

						if($data===null)
							$related->insert();
						else
							$this->insert($data,$related);
					break;
					case CActiveRecord::HAS_MANY:
						$relatedTableSchema=CActiveRecord::model($relatedClass)->getTableSchema();
						$fks=preg_split('/\s*,\s*/',$foreignKey,-1,PREG_SPLIT_NO_EMPTY);
						$map=array();

						foreach($fks as $i=>$fk)
						{
							if(!isset($relatedTableSchema->columns[$fk]))
								throw new CDbException(Yii::t('yiiext','The relation "{relation}" in active record class "{class}" is specified with an invalid foreign key "{key}". There is no such column in the table "{table}".',
									array('{class}'=>get_class($owner),'{relation}'=>$name,'{key}'=>$fk,'{table}'=>$relatedTableSchema->name)));

							if(isset($relatedTableSchema->foreignKeys[$fk]) && $schema->compareTableNames($ownerTableSchema->rawName,$relatedTableSchema->foreignKeys[$fk][0]))
								$pk=$relatedTableSchema->foreignKeys[$fk][1];
							else // FK constraints undefined
							{
								if(is_array($ownerTableSchema->primaryKey)) // composite PK
									$pk=$ownerTableSchema->primaryKey[$i];
								else
									$pk=$ownerTableSchema->primaryKey;
							}

							$map[$pk]=$fk;
						}

						foreach($related as $model)
						{
							foreach($map as $pk=>$fk)
								$model->$fk=$owner->$pk;

							if($data===null)
								$model->insert();
							else
								$this->insert($data,$model);
						}
					break;
					case CActiveRecord::MANY_MANY:
						if(!preg_match('/^\s*(.*?)\((.*)\)\s*$/',$foreignKey,$matches))
							throw new CDbException(Yii::t('yiiext','The relation "{relation}" in active record class "{class}" is specified with an invalid foreign key. The format of the foreign key must be "joinTable(fk1,fk2,...)".',
								array('{class}'=>get_class($owner),'{relation}'=>$name)));

						if(($joinTable=$schema->getTable($matches[1]))===null)
							throw new CDbException(Yii::t('yiiext','The relation "{relation}" in active record class "{class}" is not specified correctly: the join table "{joinTable}" given in the foreign key cannot be found in the database.',
								array('{class}'=>get_class($owner),'{relation}'=>$name,'{joinTable}'=>$matches[1])));

						$relatedTableSchema=CActiveRecord::model($relatedClass)->getTableSchema();
						$fks=preg_split('/\s*,\s*/',$matches[2],-1,PREG_SPLIT_NO_EMPTY);
						$ownerMap=array();
						$relatedMap=array();
						$fkDefined=true;

						foreach($fks as $i=>$fk)
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

						foreach($related as $model)
						{
							if($data===null)
								$model->insert();
							else
								$this->insert($data,$model);

							$joinTableAttributes=array();

							foreach($ownerMap as $pk=>$fk)
								$joinTableAttributes[$fk]=$owner->$pk;

							foreach($relatedMap as $pk=>$fk)
								$joinTableAttributes[$fk]=$model->$pk;

							$builder->createInsertCommand($joinTable,$joinTableAttributes)->execute();
						}
					break;
				}
			}

			if($extTransFlag===null)
				$transaction->commit();

			return true;
		}
		catch(Exception $e)
		{
			if($extTransFlag===null)
				$transaction->rollBack();

			throw $e;
		}
	}

	/**
	 * Update main model and all it's related models recursively.
	 * @param array $data attributes and relations.
	 * @param CActiveRecord $owner for internal needs.
	 * @return boolean whether the update is successful.
	 */
	public function update($data=null,$owner=null)
	{
		if($owner===null)
			$owner=$this->getOwner();

		$db=$owner->getDbConnection();
		$extTransFlag=$db->getCurrentTransaction();

		if($extTransFlag===null)
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
				$attributeNames=$owner->attributeNames();
				$attributes=array_intersect($data,$attributeNames);

				if($attributes===array())
					$attributes=null;

				$newData=array_diff($data,$attributeNames);
			}

			$result=$owner->update($attributes);

			foreach($newData as $name=>$data)
			{
				if(!is_array($data))
					$name=$data;

				if(!$owner->hasRelated($name))
					continue;

				$related=$owner->getRelated($name);

				if(is_array($related))
				{
					foreach($related as $model)
					{
						if(is_array($data))
						{
							if($model->getIsNewRecord())
								$this->insert($data,$model);
							else
								$this->update($data,$model);
						}
						else
						{
							if($model->getIsNewRecord())
								$model->insert();
							else
								$model->update();
						}
					}
				}
				else
				{
					if(is_array($data))
					{
						if($related->getIsNewRecord())
							$this->insert($data,$related);
						else
							$this->update($data,$related);
					}
					else
					{
						if($related->getIsNewRecord())
							$related->insert();
						else
							$related->update();
					}
				}
			}

			if($extTransFlag===null)
				$transaction->commit();
		}
		catch(Exception $e)
		{
			if($extTransFlag===null)
				$transaction->rollBack();

			throw $e;
		}

		return $result;
	}

	/**
	 * Save main model and all it's related models recursively.
	 * @param bool  $runValidation whether to perform validation before saving the record.
	 * @param array $data attributes and relations.
	 * @return boolean whether the saving succeeds.
	 */
	public function save($runValidation=true,$data=null)
	{
		if(!$runValidation || $this->validate($data))
			return $this->getOwner()->getIsNewRecord() ? $this->insert($data) : $this->update($data);
		else
			return false;
	}
}