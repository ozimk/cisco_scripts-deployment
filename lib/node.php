<?php
##########################################################################################
# The Node class is able to represent an entity that has connections from interfaces to other Nodes
# o id: the name of the node
# o interface_ids: maps a node to he interface it is connected to on this node
# o adjacent_nodes: maps this nodes interface to the node it is connected to
# o adjacent_interfaces: maps this nodes interface to a interface of an adjacent node
# o interface types: maps teh interface to its type
# o interface networks: maps thei nterface to the index of its network
#############################################################################################
class Node {
    public $id, $vrf_name;
    private $interface_ids, $adjacent_nodes, $adjacent_interfaces, $interface_types, $interface_networks;

    function __construct($id, $vrf_name){
        $this->id = $id;
        $this->vrf_name = $vrf_name;
        $this->interface_ids = [];
        $this->adjacent_nodes = [];
        $this->interface_types = [];
        $this->interface_networks = [];
    }
    function Node($id, $vrf_name){
        $this->id = $id;
        $this->vrf_name = $vrf_name;
        $this->interface_ids = [];
        $this->adjacent_nodes = [];
        $this->interface_types = [];
        $this->interface_networks = [];
    }

    function AddConnection($interface_id, $to_node, $to_interface_id, $interface_type, $interface_network){
        if(!in_array($interface_id,$this->interface_ids)){
            if (!isset($this->interface_ids[$to_node->id])) { $this->interface_ids[$to_node->id] = [];}
            array_push($this->interface_ids[$to_node->id], $interface_id);
            $this->adjacent_nodes[$interface_id] = $to_node;
            $this->interface_types[$interface_id] = $interface_type;
            $this->adjacent_interfaces[$interface_id] = $to_interface_id;
            $this->interface_networks[$interface_id] = $interface_network;
        }
    }

    function CountInterfaces() {
        return count($this->interface_ids);
    }

    function GetAllInterfaceIDs(){
        $result = [];
        foreach ($this->interface_ids as $node => $intids) {
            foreach($intids as $id){
                array_push($result, $id);
            }
            
        }
        return $result;
    }

    function HasInterfaceID($id){
        return isset($this->interface_types[$id]);
    }

    function GetLoopbacks(){
        $result = [];
        foreach ($this->interface_ids as $node_id => $intids) {
            if($node_id == $this->id){
                foreach($intids as $id){
                    if(!(strpos($this->GetInterfaceType($id), "ex") !== false)){
                        array_push($result, $id);
                    }
                }
            }  
        }
        return $result;
    }

    function GetInterfaceNetwork($interface_id){
        return $this->interface_networks[$interface_id];
    }

    function GetInterfaceType($interface_id){
        return $this->interface_types[$interface_id];
    }

    function GetAdjacentNode($interface_id){
        return $this->adjacent_nodes[$interface_id];
    }
    function GetInterfaceIDs($to_node_id) {
        return $this->interface_ids[$to_node_id];
    }
    ######################################################
    #Get Connection
    # returns the adjacent node and interface for the given interface
    ########################################################
    function GetConnection($interface_id){
        $result = [];
        $result["node"] = $this->GetAdjacentNode($interface_id);
        $interface_ids = $result["node"]->GetInterfaceIDs($this->id);
        if(in_array($this->adjacent_interfaces[$interface_id],$interface_ids )){
            $result["interface_id"] = $this->adjacent_interfaces[$interface_id];
            return $result;
        }
        throw new ErrorException("Unreciprocated Connection: {$result["node"]->id} does not have connection to $interface_id");
    }
}
?>