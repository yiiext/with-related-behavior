<?php
class Article extends CActiveRecord
{
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return 'article';
	}

	public function behaviors()
	{
		return array(
			'withRelated'=>'ext.WithRelatedBehavior',
		);
	}

	public function relations()
	{
		return array(
			'createdBy'=>array(self::BELONGS_TO,'User','created_by_id'),
			'updatedBy'=>array(self::BELONGS_TO,'User','updated_by_id'),
			'comments'=>array(self::HAS_MANY,'Comment','article_id'),
			'tags'=>array(self::MANY_MANY,'Tag','article_tag(article_id,tag_id)'),
		);
	}

	public function rules()
	{
		return array(
			array('title','required'),
		);
	}
}