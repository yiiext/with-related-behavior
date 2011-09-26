WithRelatedBehavior
===================

Это поведение позволяет проводить валидацию/вставку/обновление модели и всех её связанных записей. Поддерживаются все типы связей.

Установка и настройка
---------------------

Скопируйте поведение в каталог extensions/wr вашего приложения и подключите в модели следующим образом:

~~~
[php]
public function behaviors()
{
	return array(
		'withRelated'=>array(
			'class'=>'ext.wr.WithRelatedBehavior',
		),
	);
}
~~~

Использование
-------------

На текущий момент документация в процессе написания, поэтому будет показано несколько примеров использования.

###Валидация

~~~
[php]
$article=new Article;
$article->title='Test';

$comment1=new Comment;
$comment2=new Comment;
$comment3=new Comment;

$article->comments=array($comment1,$comment2,$comment3);

$comment1->content='Test';
$comment2->content='Test';
$comment3->content='Test';

$article->createdBy=new User;
$article->createdBy->name='Test';

$article->createdBy->group=new Group;
$article->createdBy->group->name='Test';

$article->withRelated->validate(array('comments'=>array('content'),'createdBy'=>array('name','group'=>array('name'))));
~~~

###Вставка

~~~
[php]
$user=new User;
$user->name='Test';

$user->group=new Group;
$user->group->name='Test';

$tag1=new Tag;
$tag1->createdBy=$user;
$tag1->name='test1';
$tag2=new Tag;
$tag2->createdBy=$user;
$tag2->name='test2';
$tag3=new Tag;
$tag3->createdBy=$user;
$tag3->name='test3';

$article=new Article;
$article->title='Test';
$article->tags=array($tag1,$tag2,$tag3);

$comment1=new Comment;
$comment1->content='Test1';
$comment2=new Comment;
$comment2->content='Test2';
$comment3=new Comment;
$comment3->content='Test3';

$article->comments=array($comment1,$comment2,$comment3);

$article->createdBy=$user;

$article->withRelated->insert(array('comments','tags'=>array('createdBy'),'createdBy'=>array('id','group_id','name','group'=>array('id','name'))));
~~~

###Обновление (пример 1)

~~~
[php]
$article=Article::model()->findByPk(1);
$article->title='article1 updated';

$tag1=new Tag;
$tag1->name='tag1';
$tag2=new Tag;
$tag2->name='tag2';

$article->tags=array($tag1,$tag2);
$article->withRelated->update(array('tags'));
~~~

###Обновление (пример 2)

~~~
[php]
$article=Article::model()->with('tags')->findByPk(1);
$article->title='article1 updated';

$tags=$article->tags;
$tags[0]->name='tag1 updated';
$tags[1]->name='tag2 updated';

$article->tags=$tags;
$article->withRelated->update(array('tags'));
~~~

**Примечание:** вместо методов insert() и update() можно пользоваться методом save() по аналогии с CActiveRecord.