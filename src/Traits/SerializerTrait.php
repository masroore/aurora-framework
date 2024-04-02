<?php

namespace Aurora\Traits;

trait SerializerTrait
{
    /**
     * @var bool
     */
    protected $igBinarySupported;

    /**
     * @param mixed $data        Data to serialize
     * @param bool  $useIgBinary Use igBinary extension if supported
     *
     * @return string
     */
    protected function serialize($data, $useIgBinary = true)
    {
        if ($this->isIgBinarySupported() && $useIgBinary) {
            return igbinary_serialize($data);
        }

        return serialize($data);
    }

    /**
     * @param mixed     $data        Data to unserialize
     * @param bool|true $useIgBinary Use igBinary extension if supported
     */
    protected function unserialize($data, $useIgBinary = true)
    {
        if ($this->isIgBinarySupported() && $useIgBinary) {
            return igbinary_unserialize($data);
        }

        return unserialize($data);
    }

    /**
     * @return bool Is igBinary Supported
     */
    protected function isIgBinarySupported()
    {
        if (null === $this->igBinarySupported) {
            $this->igBinarySupported = \function_exists('igbinary_serialize');
        }

        return $this->igBinarySupported;
    }
}
