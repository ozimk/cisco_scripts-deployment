<?php
#######################################################################
#
# Some Simple Data strucutres to hold ACL, PRefix adn ORute Map Rules
# Used for the cosntrut configs script
# Created to not interfere with the complicated ACl strucure used on marking
#########################################################################

class ArbitraryText {
    public $statements;
    function ParseFromText($lines, $delimiter)
    {
        $this->statements = explode($delimiter, $lines);
    }

    function ConstructConfigString()
    {
        $config = "";
        foreach ($this->statements as $line) {
            $config .= "$line\n";
        }

        return $config;
    }
}


class ACLText
{
    public $extended, $name, $statements;

    function ParseFromText($statements, $delimiter)
    {
        $this->statements = explode($delimiter, $statements);
    }

    function ConstructConfigString()
    {
        $config = $this->extended ? "access-list extended $this->name\n" : "access-list standard $this->name\n";
        foreach ($this->statements as $rule) {
            $config .= "$rule\n";
        }


        return $config;
    }

}

class PrefixText
{
    public $name, $statements;
    function ParseFromText($statements, $delimiter)
    {
        $this->statements = explode($delimiter, $statements);
    }

    function ConstructConfigString()
    {
        $config = "";
        $pretext = "ip prefix-list $this->name ";
        foreach ($this->statements as $rule) {
            $config .= "$pretext $rule\n";
        }

        return $config;
    }
}


class RouteMapText
{
    public $name, $permit, $number, $statements;

    function ParseFromText($statements, $delimiter)
    {
        $this->statements = explode($delimiter, $statements);
    }

    function ConstructConfigString()
    {
        $config = "route-map $this->name ";
        $config .= $this->permit ? "permit " : "deny ";
        $config .= "$this->number\n";
        foreach ($this->statements as $statement) {
            $config .= "$statement\n";
        }

        return $config;
    }
}

?>