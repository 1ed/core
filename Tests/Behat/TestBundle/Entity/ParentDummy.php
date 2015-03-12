<?php

/*
 * This file is part of the DunglasJsonLdApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dunglas\JsonLdApiBundle\Tests\Behat\TestBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Parent Dummy.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @ORM\MappedSuperclass
 */
class ParentDummy
{
    /**
     * @var int The age.
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    private $age;

    public function getAge()
    {
        return $this->age;
    }
}
