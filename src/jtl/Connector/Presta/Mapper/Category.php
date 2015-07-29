<?php
namespace jtl\Connector\Presta\Mapper;

class Category extends BaseMapper
{
	protected $endpointModel = '\Category';
    protected $identity = 'id|id_category';

	protected $pull = array(
		'id' => 'id_category',
		'parentCategoryId' => 'id_parent',
		'isActive' => 'active',
		'sort' => 'position',
		'level' => 'level_depth',
		'i18ns' => 'CategoryI18n',
		'invisibilities' => 'CategoryInvisibility'
	);

    protected $push = array(
        'id_category' => 'id',
        'id_parent' => 'parentCategoryId',
        'active' => 'isActive',
        'position' => 'sort',
        //'i18ns' => 'CategoryI18n',
        //'invisibilities' => 'CategoryInvisibility'
    );
}
