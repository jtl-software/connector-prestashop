<?php

namespace jtl\Connector\Presta\Mapper;

use Jtl\Connector\Core\Model\Identity;

class Category extends BaseMapper
{
    protected ?string $endpointModel = '\Category';
    protected string $identity       = 'id|id_category';

    protected array $pull = [
        'id'               => 'id_category',
        'parentCategoryId' => null,
        'isActive'         => 'active',
        'sort'             => 'position',
        'level'            => 'level_depth',
        'i18ns'            => 'CategoryI18n',
        'invisibilities'   => 'CategoryInvisibility'
    ];

    protected array $push = [
        'id_category'          => 'id',
        'id_parent'            => null,
        'active'               => 'isActive',
        'position'             => 'sort',
        'CategoryI18n'         => 'i18ns',
        'CategoryInvisibility' => 'invisibilities'
    ];

    protected function parentCategoryId($data): Identity
    {
        if (($data['id_parent'] == \Category::getRootCategory()->id) || $data['id_parent'] == 2) {
            return new Identity(null);
        }
        return new Identity($data['id_parent']);
    }

    //phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    protected function id_parent($data)
    {
        return $data->getParentcategoryId()->getEndpoint() == 0 ? \Category::getRootCategory(
        )->id : $data->getParentcategoryId()->getEndpoint();
    }
}
