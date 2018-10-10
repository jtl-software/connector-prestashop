<?php
namespace jtl\Connector\Presta\Mapper;

class CategoryInvisibility extends BaseMapper
{
    protected $pull = array(
        'categoryId' => 'id_category',
        'customerGroupId' => 'id_group'
    );
}
