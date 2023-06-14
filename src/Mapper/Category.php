<?php

namespace jtl\Connector\Presta\Mapper;

use jtl\Connector\Model\Identity;

class Category extends BaseMapper
{
    protected $endpointModel = '\Category';
    protected $identity      = 'id|id_category';

    protected $pull = [
        'id' => 'id_category',
        'parentCategoryId' => null,
        'isActive' => 'active',
        'sort' => 'position',
        'level' => 'level_depth',
        'i18ns' => 'CategoryI18n',
        'invisibilities' => 'CategoryInvisibility'
    ];

    protected $push = [
        'id_category' => 'id',
        'id_parent' => null,
        'active' => 'isActive',
        'position' => 'sort',
        'CategoryI18n' => 'i18ns',
        'CategoryInvisibility' => 'invisibilities'
    ];

    protected function parentCategoryId($data)
    {
        if (($data['id_parent'] == \Category::getRootCategory()->id) || $data['id_parent'] == 2) {
            return new Identity(null);
        }
        return new Identity($data['id_parent']);
    }

    protected function id_parent($data)
    {
        return $data->getParentcategoryId()->getEndpoint() == 0 ? \Category::getRootCategory()->id : $data->getParentcategoryId()->getEndpoint();
    }
}
