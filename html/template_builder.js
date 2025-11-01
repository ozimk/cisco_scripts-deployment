function build(json, context) {
    let template_form = document.getElementById("topology");

    // Network
    let network_container = CreateFieldContainer("Networks", template_form, 'network');
    CreateButton(function () { AddNetwork(null, network_container); }, "Add New Network", template_form, "network-add", 'item-add');
    CreateButton(function () { RemoveNetwork(network_container); }, 'Remove Network', template_form, "network-remove", 'item-remove');

    //Mapping
    let mapping_container = CreateFieldContainer("Mappings", template_form, 'mapping');
    CreateButton(function () { AddMapping(null, null, mapping_container, context); }, "Add New Mapping", template_form, "mapping-add", 'item-add');
    CreateButton(function () { RemoveMapping(mapping_container, context); }, 'Remove Mapping', template_form, "mapping-remove", 'item-remove');

    // Models
    let model_container = CreateFieldContainer("Models", template_form, 'model');
    CreateButton(function () { AddModel(null, model_container, null, null, null, null, network_container, mapping_container, context); }, "Add New Model", template_form, "model-add", 'item-add');


    //Routing
    let routing_container = CreateFieldContainer("Routing", template_form, 'routing');
    CreateButton(function () { AddRouting(null, null, routing_container); }, "Add New Routing", template_form, "routing-add", 'item-add');

    // Components
    let components_container = CreateFieldContainer("Components", template_form, 'component');
    CreateButton(function () { AddComponent(null, null, components_container); }, "Add New Component", template_form, "component-add", 'item-add');

    let completed_components = [];
    let completed_route = [];

    if (json != null) {
        for (let i in json["Networks"]) {
            AddNetwork(json["Networks"][i], network_container);
        }
        for (let i in json["Models"]) {
            let model = json["Models"][i];
            AddModel(model, model_container, json[model]["Interfaces"], json[model]["Routing"], json[model]["Components"], json["Masks"], network_container, mapping_container, context);
            for (let r in json[model]["Routing"]) {
                let route = json[model]["Routing"][r];
                if (!completed_route.includes(r)) {
                    AddRouting(route, json[route], routing_container);
                    completed_route.push(r);
                }

            }
            for (let c in json[model]["Components"]) {
                let component = json[model]["Components"][c];
                if (!completed_components.includes(c)) {
                    AddComponent(component, json[component], components_container);
                    completed_components.push(c);
                }

            }
        }
        for (let i in json["Mappings"]) {
            AddMapping(i, json["Mappings"][i], mapping_container, context);
        }
        //need to load the connection after the interfaces are made
        for (let i in json["Models"]) {
            for (let detail_string of json[json["Models"][i]]["Interfaces"]) {
                details = detail_string.split(":");
                LoadCon(json["Models"][i], details[1], details[2], details[3], details[0])
            }
        }
    }

    CheckErrorInterfaceValue(context);
    CheckErrorComponent();
    CheckErrorRouting();

}

////////////////////////////////////////////////////////////////
//Networks
///////////////////////////////////////////////////////////////
function AddNetwork(network, container) {
    let index = container.children.length;
    let network_input = CreateLabeledInput(`Network ${index}`, "text", container, `networks-${index}`, `networks[${index}]`, "div", 'name-input');
    network_input.pattern = "^(?:(?:25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9][0-9]|[0-9])\.){3}(?:25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9][0-9]|[0-9])$";
    if (network != null) { network_input.value = network; }
}

function RemoveNetwork(network_container) {
    let lastChild = network_container.lastChild;
    network_container.removeChild(lastChild);
}

////////////////////////////////////////////////////////////////
// Mappings
///////////////////////////////////////////////////////////////
function AddMapping(router, model, container, context) {
    let index = container.children.length;
    let div = document.createElement("div");
    let router_input = CreateLabeledInput("Router:", "text", div, `mapping-${index}-router`, `mapping[${index}][router]`, "span", 'name-input');
    let model_input = CreateLabeledInput("Model:", "text", div, `mapping-${index}-model`, `mapping[${index}][model]`, "span", 'name-input');
    container.appendChild(div);
    if (router != null && model != null) {
        router_input.value = router; model_input.value = model;
        CheckErrorModelMapping(document.getElementById("model"), container);
        CheckErrorRouterMapping(context, router_input);
    }
    router_input.addEventListener("change", () => { CheckErrorRouterMapping(context, router_input); CheckErrorInterfaceValue(context); });
    model_input.addEventListener("change", () => { CheckErrorModelMapping(document.getElementById("model"), container); CheckErrorInterfaceValue(context); })
}

function RemoveMapping(mapping_container, context) {
    let lastChild = mapping_container.lastChild;
    mapping_container.removeChild(lastChild);
    CheckErrorInterfaceValue(context);
}

////////////////////////////////////////////////////////////////
// Models
////////////////////////////////////////////////////////////////
function AddModel(name, model_container, interfaces, routing, components, masks, network_container, mappings_container, context) {
    if (name == null) {
        name = prompt("Enter Name of Model", `Model${model_container.children.length}`) ?? "";
    }
    name = name.trim();
    if (name == "" || CheckErrorModelName(name, model_container)) { return; }



    let model_field = ConfigFieldset(model_container, 'model', name, RemoveModel);

    //Interfaces
    let interface_container = CreateIndexedContainer(model_field, "Interfaces (direction | network | mask): ", (interface_container) => { AddInterface(null, interface_container, masks, network_container, mappings_container, context) }, `${model_field.id}-interfaces`, `${model_field.name}[Interfaces]`);

    // Routing titles
    let routing_container = CreateIndexedContainer(model_field, "Routing (title): ", (routing_container) => { AddRoutingTitle(null, routing_container) }, `${model_field.id}-routing`, `${model_field.name}[Routing]`);

    // Component titles
    let components_container = CreateIndexedContainer(model_field, "Components (title): ", (components_container) => { AddComponentTitle(null, components_container) }, `${model_field.id}-components`, `${model_field.name}[Components]`);


    for (let i in interfaces) {
        AddInterface(interfaces[i], interface_container, masks, network_container, mappings_container, context);
    }
    for (let i in routing) {
        AddRoutingTitle(routing[i], routing_container);
    }
    for (let i in components) {
        AddComponentTitle(components[i], components_container);
    }

    CheckErrorModelMapping(model_container, document.getElementById("mapping"));
}

function RemoveModel(model_field) {

    let remove = confirm("Remove Model?");
    if (!remove) { return; }

    for (interface_div of Array.from(document.getElementById(`${model_field.id}-interfaces`).children)) {
        RemoveInterface(interface_div, null, false);
    }
    let container = model_field.parentElement;
    model_field.parentElement.removeChild(model_field);

    CheckErrorModelMapping(container, document.getElementById("mapping"));
}

// Routing Titles
function AddRoutingTitle(component_title, container) {
    let div = CreateContainer(container, `${container.id}-${container.index}`, `${container.name}[${container.index}]`);
    container.index++;
    div.connected = false;

    CreateButton(() => { RemoveRoutingTitle(div) }, "-", div, null, 'item-remove');


    let title = CreateInput("text", div, `${div.id}-title`, `${container.name}[]`, 'name-input');
    title.addEventListener("change", function () { CheckErrorRouting() })

    container.appendChild(div);
    if (component_title != null) {
        title.value = component_title;
        CheckErrorRouting();
    }
}

function RemoveRoutingTitle(routingtitle_div) {
    routingtitle_div.parentElement.removeChild(routingtitle_div);
    CheckErrorRouting();
}

// Component Titles
function AddComponentTitle(component_title, container) {
    let div = CreateContainer(container, `${container.id}-${container.index}`, `${container.name}[${container.index}]`);
    container.index++;
    div.connected = false;

    CreateButton(() => { RemoveComponentTitle(div) }, "-", div, null, 'item-remove');

    let title = CreateInput("text", div, `${div.id}-title`, `${container.name}[]`, 'name-input');
    title.addEventListener("change", function () { CheckErrorComponent() });

    container.appendChild(div);
    if (component_title != null) {
        title.value = component_title;
        CheckErrorComponent();
    }
}

function RemoveComponentTitle(componenttitle_div) {
    componenttitle_div.parentElement.removeChild(componenttitle_div);
    CheckErrorComponent();
}

// Interfaces
function AddInterface(detail_string, container, masks, network_container, mappings_container, context) {
    //Create Single INterface Container
    let div = CreateContainer(container, `${container.id}-${container.index}`, `${container.name}[${container.index}]`);
    container.index++;
    div.connected = false;


    CreateButton(() => { RemoveInterface(div, context) }, "-", div, null, 'item-remove');

    container.appendChild(div);
    //Direction Input
    let dir_input = CreateInput("number", div, `${div.id}-direction`, `${div.name}[direction]`, 'direction-input');
    dir_input.addEventListener('change', function () { CheckErrorInterfaceValue(context); });

    for (let child of Array.from(mappings_container.children)) {
        let model_input = child.lastChild;
        let router_input = child.firstChild;
        model_input.addEventListener('change', function () { CheckErrorInterfaceValue(context); })
        router_input.addEventListener('change', function () { CheckErrorInterfaceValue(context); })
    }

    //Network Input
    let network_input = document.createElement("select");
    network_input.name = `${div.name}[network]`
    network_input.id = `${div.id}-network`;
    network_input.className = "network-dropdown";
    div.appendChild(network_input);

    let net_add_button = document.getElementById("network-add");
    let net_remove_button = document.getElementById("network-remove");
    net_add_button.addEventListener("click", function () { UpdateNetOptions(network_input, network_container) });
    net_remove_button.addEventListener("click", function () { UpdateNetOptions(network_input, network_container) });
    UpdateNetOptions(network_input, network_container);

    //Mask Input
    let mask_input = CreateInput("number", div, `${div.id}-mask`, `${div.name}[mask]`, 'direction-input');
    mask_input.max = 32;

    //ConnectButton
    CreateConnectButton(div, true, function () { Connect(div, null) });
    if (detail_string != null) {
        let details = detail_string.split(":");
        dir_input.value = details[1];
        document.getElementById(`${network_input.id}-${details[4]}`).selected = true;
        mask_input.value = masks[details[0].split("-")[0]];
    }



}

function RemoveInterface(interface_div, context, ask = true) {

    if (ask) {
        let remove = confirm("Remove Interface");
        if (!remove) { return; }
    }

    if (interface_div.connected) {
        let device_to = document.getElementById(`${interface_div.id}-device_connection`).value;
        let interface_to = document.getElementById(`${interface_div.id}-interface_connection`).value;
        if (device_to == null || device_to.trim() == "" || interface_to == null | interface_to.trim() == "") { return; }

        parent_to = document.getElementById(`model-${device_to}-interfaces`);
        let div_to = null;
        if (parent_to == null) { return; }
        for (child of Array.from(parent_to.children)) {
            let direction_input = document.getElementById(`${child.id}-direction`);
            if (direction_input == null) { continue; }
            if (direction_input.value == interface_to) {
                div_to = child;
                break;
            }
        }

        if (div_to != null && div_to.connected) {
            Disconnect(interface_div, div_to)
        }
    }

    interface_div.parentElement.removeChild(interface_div);

    if (context != null)
        CheckErrorInterfaceValue(context);
}

/////////////////////////////////////////////////////////////////////////////
// Routing
/////////////////////////////////////////////////////////////////////////////
function AddRouting(name, details, routing_container) {

    if (name == null) {
        name = prompt("Enter Routing Name");
    }
    if (name == "" || CheckErrorRoutingName(name.trim(), routing_container)) { return; }


    let routing_field = ConfigFieldset(routing_container, 'routing', name, (routing_field) => { RemoveRouting(routing_field); });

    let protocol_select = document.createElement("select");
    protocol_select.name = `${routing_field.name}[protocol]`;
    protocol_select.className = 'dropdown';
    CreateOption("BGP", "bgp", protocol_select);
    CreateOption("OSPF", "ospf", protocol_select);
    routing_field.appendChild(protocol_select);

    CreateContainer(routing_field, `${routing_field.id}-details`);

    if (details != null) {
        let name = details["protocol"];
        for (var i = 0; i < protocol_select.options.length; i++) {
            if (protocol_select.options[i].value === name) {
                protocol_select.selectedIndex = i;
                break;
            }
        }
    }

    CreateRoutingDetails(protocol_select, details);
    protocol_select.addEventListener("change", function () { CreateRoutingDetails(protocol_select, null); })

    CheckErrorRouting();
}

function CreateRoutingDetails(protocol_select, details) {
    let old_container = document.getElementById(`${protocol_select.parentElement.id}-details`)
    let container = old_container.cloneNode(false);
    old_container.parentElement.replaceChild(container, old_container);

    let router_id_input = CreateLabeledInput("Router ID", "text", container, `${container.parentElement.id}-router_id`, `${container.parentElement.name}[router_id]`, "div", 'name-input');
    router_id_input.pattern = "^(?:(?:25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9][0-9]|[0-9])\.){3}(?:25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9][0-9]|[0-9])$";
    if (details != null) { router_id_input.value = details["router_id"]; }

    switch (protocol_select.value) {
        case "bgp":
            BGPDetails(container, details);
            break;
        case "ospf":
            OSPFDetails(container, details);
            break;
    }

    //advertised
    let advert_container = document.createElement("div");
    advert_container.id = `${container.parentElement.id}-advert`;
    advert_container.name = `${container.parentElement.name}[advert]`;
    container.appendChild(advert_container);
    let advert_title = CreateText("Advertisements (interface):", "div", advert_container);
    CreateButton(function () { AddAdvertisement(null, advert_container); }, "+", advert_title);
    CreateButton(function () { RemoveAdvertisement(advert_container); }, "-", advert_title);
    if (details != null) {
        for (let i = 0; i < details["Advertised"].length; i++) {
            AddAdvertisement(details["Advertised"][i], advert_container);
        }

    }

}

function BGPDetails(container, details) {
    let as_input = CreateLabeledInput("AS Start", "number", container, `${container.parentElement.id}-as`, `${container.parentElement.name}[as]`, "span", 'name-input');
    let as_increment = CreateLabeledInput("Increment AS After", "text", container, `${container.parentElement.id}-as_increment`, `${container.parentElement.name}[as_increment]`, "span", 'name-input');
    as_increment.placeholder = "Model1,Model4";
    let update_source_input = CreateLabeledInput("Update Source", "number", container, `${container.parentElement.id}-update_source`, `${container.parentElement.name}[update_source]`, "span", 'direction-input');
    update_source_input.setAttribute("style", "width:35px");
    if (details != null) {
        update_source_input.value = details["update_source"];
        as_input.value = details["as"];
        as_increment.value = details["increment_as_after"];
    }
}

function OSPFDetails(container, details) {
    let proc_id_input = CreateLabeledInput("Process ID", "number", container, `${container.parentElement.id}-proc_id`, `${container.parentElement.name}[proc_id]`, "span", 'name-input');
    if (details != null) { proc_id_input.value = details["proc_id"]; }
}

function AddAdvertisement(advert, advert_container) {
    let ad_input = CreateInput("number", advert_container, `${advert_container.id}-${advert_container.children.length}`, `${advert_container.name}[${advert_container.children.length}]`, 'direction-input');
    if (advert != null) { ad_input.value = advert; }
    return ad_input;
}

function RemoveAdvertisement(ad_container) {
    let lastChild = ad_container.lastChild;
    ad_container.removeChild(lastChild);
}

function RemoveRouting(routing_field) {
    let remove = confirm("Remove Routing?");
    if (!remove) { return; }
    routing_field.parentElement.removeChild(routing_field);
    CheckErrorRouting();
}

////////////////////////////////////////////////////////////////////////////////
// Components
////////////////////////////////////////////////////////////////////////////////
function AddComponent(name, details, component_container) {
    if (name == null) {
        name = prompt("Enter Component Name");
    }

    if (name == "" || CheckErrorComponentName(name.trim(), component_container)) { return; }

    let component_field = ConfigFieldset(component_container, `component`, name, (component_field) => { RemoveComponent(component_field); })


    let config_select = document.createElement("select");
    config_select.name = `${component_field.name}[config]`;
    config_select.className = 'dropdown';
    CreateOption("SYSLOG", "syslog", config_select);
    CreateOption("SNMP", "snmp", config_select);
    CreateOption("ACL", "acl", config_select);
    CreateOption("Route Map", "route_map", config_select);
    CreateOption("Prefix List", "prefix", config_select);
    CreateOption("NTP", "ntp", config_select);
    CreateOption("Arbitrary", "arbitrary", config_select);
    component_field.appendChild(config_select);

    let org_div = document.createElement("div");
    org_div.id = `${component_field.id}-org`;
    org_div.name = `${component_field.name}[org]`;
    let room_input = CreateLabeledInput("Room", "text", org_div, `${org_div.id}-room`, `${org_div.name}[room]`, "span", 'name-input');
    room_input.placeholder = "All";
    let rack_input = CreateLabeledInput("Rack", "text", org_div, `${org_div.id}-rack`, `${org_div.name}[rack]`, "span", 'name-input');
    rack_input.placeholder = "Black";
    let kit_input = CreateLabeledInput("Kit", "text", org_div, `${org_div.id}-kit`, `${org_div.name}[kit]`, "span", 'name-input');
    kit_input.placeholder = "Blue,Green";
    component_field.appendChild(org_div);


    if (details != null) {
        let config = details["config"];
        for (var i = 0; i < config_select.options.length; i++) {
            if (config_select.options[i].value === config) {
                config_select.selectedIndex = i;
                break;
            }
        }

        room_input.value = details["room"];
        rack_input.value = details["rack"];
        kit_input.value = details["kit"];
    }

    let details_container = document.createElement("div");
    details_container.id = `${component_field.id}-details`;
    component_field.appendChild(details_container);
    CreateComponentDetails(config_select, details);
    config_select.addEventListener("change", function () { CreateComponentDetails(config_select, null); })

    CheckErrorComponent();
}

function CreateComponentDetails(config_select, details) {
    let old_container = document.getElementById(`${config_select.parentElement.id}-details`)
    let container = old_container.cloneNode(false);
    old_container.parentElement.replaceChild(container, old_container);

    switch (config_select.value) {
        case "syslog":
            SyslogDetails(container, details);
            break;
        case "snmp":
            SNMPDetails(container, details);
            break;
        case "acl":
            ACLDetails(container, details);
            break;
        case "prefix":
            PrefixDetails(container, details);
            break;
        case "route_map":
            RouteMapDetails(container, details);
            break;
        case "ntp":
            NTPDetails(container, details);
            break;
        case "arbitrary":
            ArbitraryDetails(container, details);
            break;
    }
}

function SyslogDetails(container, details) {
    let address_input = CreateLabeledInput("Server Address", "text", container, `${container.parentElement.id}-address`, `${container.parentElement.name}[address]`, "div", 'name-input');
    address_input.pattern = "^(?:(?:25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9][0-9]|[0-9])\.){3}(?:25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9][0-9]|[0-9])$";
    let facility_input = CreateLabeledInput("Facility", "number", container, `${container.parentElement.id}-facility`, `${container.parentElement.name}[facility]`, "span", 'direction-input');
    facility_input.max = 7;
    let log_level_select = CreateSelect("Log Level", container, `${container.parentElement.id}-log_level`, `${container.parentElement.name}[log_level]`, 'dropdown');
    CreateOption("Emergency", "emerg", log_level_select);
    CreateOption("Alert", "alert", log_level_select);
    CreateOption("Critical", "crit", log_level_select);
    let error = CreateOption("Error", "err", log_level_select);
    error.selected = true;
    CreateOption("Warning", "warning", log_level_select);
    CreateOption("Notice", "notice", log_level_select);
    CreateOption("Informational", "info", log_level_select);
    CreateOption("Debug", "debug", log_level_select);
    let source_input = CreateLabeledInput("Source Interface", "number", container, `${container.parentElement.id}-source`, `${container.parentElement.name}[source]`, 'div', 'direction-input');
    let transport_input = CreateSelect("Protocol", container, `${container.parentElement.id}-transport`, `${container.parentElement.name}[transport]`, 'dropdown');
    CreateOption("Default", "default", transport_input);
    CreateOption("TCP", "tcp", transport_input);
    CreateOption("UDP", "udp", transport_input);
    let port_input = CreateLabeledInput("Port", "number", container, `${container.parentElement.id}-port`, `${container.parentElement.name}[name]`, 'span', 'name-input');
    port_input.placeholder = "ignored if transort is default";
    if (details != null) {
        address_input.value = details['address'];
        if (details['facility'] != null) { facility_input.value = details['facility']; }
        if (details['log_level'] != null) {
            let log_level_option = document.getElementById(`${log_level_select.id}-${details['log_level']}`);
            log_level_option.selected = true;
        }
        if (details['source_interface'] != null) { source_input.value = details['source_interface']; }
        if (details['port'] != null) { port_input.value = details['port']; }
        if (details['transport_protocol'] != null) {
            let transport_option = document.getElementById(`${transport_input.id}-${details['transport_protocol']}`);
            transport_option.selected = true;
        }
    }
}

function SNMPDetails(container, details) {
    let address_input = CreateLabeledInput("Server Address", "text", container, `${container.parentElement.id}-host`, `${container.parentElement.name}[host]`, "span", 'name-input');
    address_input.pattern = "^(?:(?:25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9][0-9]|[0-9])\.){3}(?:25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9][0-9]|[0-9])$";
    let transport_input = CreateSelect("Protocol", container, `${container.parentElement.id}-transport`, `${container.parentElement.name}[transport]`, 'dropdown');
    CreateOption("UDP", "udp", transport_input);
    CreateOption("TCP", "tcp", transport_input);
    let port_input = CreateLabeledInput("Port", "number", container, `${container.parentElement.id}-port`, `${container.parentElement.name}[port]`, 'span', 'name-input');
    CreateText("", 'div', container);
    let view_input = CreateLabeledInput("View Name", "text", container, `${container.parentElement.id}-view`, `${container.parentElement.name}[view]`, "span", 'name-input');
    let group_input = CreateLabeledInput("Group Name", "text", container, `${container.parentElement.id}-group`, `${container.parentElement.name}[group]`, "span", 'name-input');
    let user_input = CreateLabeledInput("User Name", "text", container, `${container.parentElement.id}-user`, `${container.parentElement.name}[user]`, "span", 'name-input');
    let auth_input = CreateLabeledInput("Auth Pass", "text", container, `${container.parentElement.id}-auth`, `${container.parentElement.name}[auth]`, "span", 'name-input');
    let enc_input = CreateLabeledInput("Enc Pass", "text", container, `${container.parentElement.id}-enc`, `${container.parentElement.name}[enc]`, "span", 'name-input');
    let permission_input = CreateLabeledInput("Permission", "text", container, `${container.parentElement.id}-permission`, `${container.parentElement.name}[permission]`, "span", 'name-input');

    let traps_check = CreateLabeledInput("Enable Traps", "checkbox", container, `${container.parentElement.id}-traps`, `${container.parentElement.name}[traps]`, "div", 'radio-input');
    traps_check.checked = true;
    let ifindex_check = CreateLabeledInput("Ifindex Persist", "checkbox", container, `${container.parentElement.id}-ifindex`, `${container.parentElement.name}[ifindex]`, "div", 'radio-input');
    ifindex_check.ifindex = true;

    let optionsText = CreateTextArea("Options (seperate on newline)", container, `${container.parentElement.id}-options`, `${container.parentElement.name}[options]`, 'textarea-input');
    optionsText.placeholder = "iso included...";

    if (details != null) {

        address_input.value = details["host"];
        port_input.value = details['port'];
        let transport_option = document.getElementById(`${transport_input.id}-${details['transport']}`);
        transport_option.selected = true;

        view_input.value = details["view"];
        group_input.value = details["group"];
        user_input.value = details["user"];
        auth_input.value = details["auth"];
        enc_input.value = details["enc"];
        permission_input.value = details["permission"];


        traps_check.checked = details['traps'];
        ifindex_check.checked = details['ifindex'];
        optionsText.value = details['options'].replaceAll("#", "\r\n");
    }
}

function ACLDetails(container, details) {
    let extended_check = CreateLabeledInput("Extended", "checkbox", container, `${container.parentElement.id}-extended`, `${container.parentElement.name}[extended]`, "div", 'radio-input');
    extended_check.checked = true;
    let acltext = CreateTextArea("Rules", container, `${container.parentElement.id}-rules`, `${container.parentElement.name}[rules]`, 'textarea-input-large');

    if (details != null) {
        extended_check.checked = details['extended'];
        acltext.value = details['rules'].replaceAll("#", "\r\n");
    }

    //attached
    let attached_title = CreateText("Attached To (interface-in|out): ", "div", container);
    let attach_interface_container = CreateContainer(container, `${container.parentElement.id}-attached`, `${container.parentElement.name}[attached]`);
    if (details != null) {
        for (let i = 0; i < details["Attached"].length; i++) {
            let att = AddAttachement(details["Attached"][i], attach_interface_container);
            att.placeholder = "{direction}-{in|out}";
        }
    }

    CreateButton(function () { AddAttachement(null, attach_interface_container); }, "+", attached_title);
    CreateButton(function () { RemoveAttachement(attach_interface_container); }, "-", attached_title);
}

function PrefixDetails(container, details) {
    let prefixtext = CreateTextArea("Rules", container, `${container.parentElement.id}-rules`, `${container.parentElement.name}[rules]`, 'textarea-input-large');

    if (details != null) {
        prefixtext.value = details['rules'].replaceAll("#", "\r\n");
    }
}

function RouteMapDetails(container, details) {
    let permit_check = CreateLabeledInput("Permit", "checkbox", container, `${container.parentElement.id}-permit`, `${container.parentElement.name}[permit]`, "div", 'radio-input');
    permit_check.checked = true;
    let num_input = CreateLabeledInput("Num", "number", container, `${container.parentElement.id}-rm_num`, `${container.parentElement.name}[rm_num]`, "div", 'direction-input');
    let routemaptext = CreateTextArea("Statements", container, `${container.parentElement.id}-statements`, `${container.parentElement.name}[statements]`, 'textarea-input-large');

    if (details != null) {
        permit_check.checked = details['permit'];
        num_input.value = details['number'];
        routemaptext.value = details['statements'].replaceAll("#", "\r\n");
    }

    //attached
    let attached_title = CreateText("Attached To neighbor (interface to nei-in/out): ", "div", container);
    let attach_interface_container = CreateContainer(container, `${container.parentElement.id}-attached`, `${container.parentElement.name}[attached]`);
    if (details != null) {
        for (let i = 0; i < details["Attached"].length; i++) {
            AddAttachement(details["Attached"][i], attach_interface_container);
        }
    }
    CreateButton(function () { AddAttachement(null, attach_interface_container); }, "+", attached_title);
    CreateButton(function () { RemoveAttachement(attach_interface_container); }, "-", attached_title);
}

function NTPDetails(container, details) {
    let server_input = CreateLabeledInput("Server IP", "text", container, `${container.parentElement.id}-ntp_server`, `${container.parentElement.name}[ntp_server]`, "div", 'name-input');
    server_input.patten = "^(?:(?:25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9][0-9]|[0-9])\.){3}(?:25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9][0-9]|[0-9])$";
    if (details != null) {
        server_input.value = details["server"];
    }
}

function ArbitraryDetails(container, details) {
    let configtext = CreateTextArea("Arbitrary", container, `${container.parentElement.id}-arbitrary`, `${container.parentElement.name}[arbitrary]`, 'textarea-input-large');

    if (details != null) {
        configtext.value = details['arbitrary'].replaceAll("#", "\r\n");
    }
}

function AddAttachement(attach, attach_container) {
    let at_input = CreateInput("text", attach_container, `${attach_container.id}-${attach_container.children.length}`, `${attach_container.name}[${attach_container.children.length}]`, 'direction-input');
    at_input.placeholder = "intdir-in|out";
    at_input.pattern = "[0-9]+-(in|out)";
    at_input.title = "Pattern must be [digits]-[in/out]"
    if (attach != null) { at_input.value = attach; }
    return at_input;
}

function RemoveAttachement(at_container) {
    let lastChild = at_container.lastChild;
    at_container.removeChild(lastChild);
}

function RemoveComponent(component_field) {
    let remove = confirm("Remove Component?");
    if (!remove) { return; }
    component_field.parentElement.removeChild(component_field);
    CheckErrorComponent();
}

/////////////////////////////////////////////////////////////////////////////
// Connections
/////////////////////////////////////////////////////////////////////////////

function Connect(div_from, div_to) {
    if (div_from != null) {
        //Create To Inputs
        div_from.connected = true;
        let device_to_input = CreateInput("text", div_from, `${div_from.id}-device_connection`, `${div_from.name}[device_connection]`, 'name-input');
        device_to_input.placeholder = "Model..."
        device_to_input.addEventListener("change", function () { Connect(null, div_from) });

        let interface_to_input = CreateInput("number", div_from, `${div_from.id}-interface_connection`, `${div_from.name}[interface_connection]`, 'direction-input');
        interface_to_input.setAttribute("style", "width:35px");
        interface_to_input.placeholder = 0;
        interface_to_input.addEventListener("change", function () { Connect(null, div_from) })

        check_div = document.createElement("span");
        check_div.id = `${div_from.id}-extint`;
        div_from.appendChild(check_div);

        CreateRadioButton(check_div, `${div_from.name}[extint]`, `${div_from.id}-in`, "Internal", "in");
        CreateRadioButton(check_div, `${div_from.name}[extint]`, `${div_from.id}-next`, "To Next System", "next");
        CreateRadioButton(check_div, `${div_from.name}[extint]`, `${div_from.id}-prev`, "To Previous System", "prev");
        check_div.firstChild.checked = true;

        // let steps_container = document.createElement("div");
        // steps_container.id = `${div_from.id}-steps_container`;
        // let steps = CreateInput('number', steps_container, `${div_from.id}-steps`, `${div_from.name}[steps]`);
        // steps.value = 1;
        // steps.max = 99;
        // steps.setAttribute("style", "width:35px");
        // let steps_label = CreateText("Systems Back", "label", steps_container);
        // steps_label.for = `${div_from.id}-steps`;
        // check_div.appendChild(steps_container);
        // steps_container.style.display = "none";

        //Change Buttoin to disconnect
        CreateConnectButton(div_from, false, function () { Disconnect(div_from, null) });

    } else if (div_to != null) {
        let device_from = document.getElementById(`${div_to.id}-device_connection`).value;
        let interface_from = document.getElementById(`${div_to.id}-interface_connection`).value;
        if (device_from == null || device_from.trim() == "" || interface_from == null || interface_from.trim() == "") { return; }

        parent_from = document.getElementById(`model-${device_from}-interfaces`);
        if (parent_from == null) { alert(`Device ${device_from} does not exist`); Disconnect(null, div_to); return; }
        for (child of Array.from(parent_from.children)) {
            let direction_input = document.getElementById(`${child.id}-direction`);
            if (direction_input == null) { continue; }
            if (direction_input.value == interface_from) {
                div_from = child;
                break;
            }
        }

        if (div_from == null) { alert(`Interface ${interface_from} does not exist on ${device_from}`); Disconnect(null, div_to); return; }
        if (div_from.connected) { alert(`Interface ${interface_from} on ${device_from} has an open connection`); Disconnect(null, div_to); return; }

        // create inputs for from_div
        div_from.connected = true;
        let device_to_input = CreateInput("text", div_from, `${div_from.id}-device_connection`, `${div_from.name}[device_connection]`, 'name-input');
        device_to_input.value = document.getElementById(`${div_to.parentElement.parentElement.id}`).id.split("-")[1];
        device_to_input.addEventListener("change", function () { Connect(null, div_from) });

        let interface_to_input = CreateInput("number", div_from, `${div_from.id}-interface_connection`, `${div_from.name}[interface_connection]`, 'direction-input');
        interface_to_input.value = document.getElementById(`${div_to.id}-direction`).value;
        interface_to_input.addEventListener("change", function () { Connect(null, div_from) })

        //both are now connected
        device_to_input.readOnly = true;
        interface_to_input.readOnly = true;
        document.getElementById(`${div_to.id}-device_connection`).readOnly = true;
        document.getElementById(`${div_to.id}-interface_connection`).readOnly = true;

        //create radio buttons
        check_div = document.createElement("span");
        check_div.id = `${div_from.id}-extint`;
        div_from.appendChild(check_div);

        CreateRadioButton(check_div, `${div_from.name}[extint]`, `${div_from.id}-in`, "Internal", "in");
        CreateRadioButton(check_div, `${div_from.name}[extint]`, `${div_from.id}-next`, "To Next System", "next");
        CreateRadioButton(check_div, `${div_from.name}[extint]`, `${div_from.id}-prev`, "To Previous System", "prev");
        check_div.firstChild.checked = true;

        // let steps_container = document.createElement("div");
        // steps_container.id = `${div_from.id}-steps_container`;
        // let steps = CreateInput('number', steps_container, `${div_from.id}-steps`, `${div_from.name}[steps]`);
        // steps.value = 1;
        // steps.max = 99;
        // steps.setAttribute("style", "width:35px");
        // let steps_label = CreateText("Systems Back", "label", steps_container);
        // steps_label.for = `${div_from.id}-steps`;
        // check_div.appendChild(steps_container);
        // steps_container.style.display = "none";

        //setup events for radiobuttons
        AttachRadioButtonConnectionEvents(div_from, div_to, `${div_from.id}-in`)
        AttachRadioButtonConnectionEvents(div_from, div_to, `${div_from.id}-prev`)
        AttachRadioButtonConnectionEvents(div_from, div_to, `${div_from.id}-next`)
        AttachRadioButtonConnectionEvents(div_to, div_from, `${div_to.id}-in`);
        AttachRadioButtonConnectionEvents(div_to, div_from, `${div_to.id}-prev`);
        AttachRadioButtonConnectionEvents(div_to, div_from, `${div_to.id}-next`);
        UpdateIntExt(div_to, div_from);
        //fix buttons so they will delete both connections
        CreateConnectButton(div_from, false, function () { Disconnect(div_from, div_to) });
        CreateConnectButton(div_to, false, function () { Disconnect(div_from, div_to) });

        //set up event listenres on the direciton and network so that the links update correctly
        let from_direction_input = document.getElementById(`${div_from.id}-direction`);
        let from_network_input = document.getElementById(`${div_from.id}-network`);
        let from_mask_input = document.getElementById(`${div_from.id}-mask`)
        from_direction_input.addEventListener('change', function () { UpdateDirectionConnection(div_from, div_to); });
        from_network_input.addEventListener('change', function () { UpdateNetworkConnection(div_from, div_to); });
        from_mask_input.addEventListener('change', function () { UpdateMaskConnection(div_from, div_to); });
        let to_direction_input = document.getElementById(`${div_to.id}-direction`);
        let to_network_input = document.getElementById(`${div_to.id}-network`);
        let to_mask_input = document.getElementById(`${div_to.id}-mask`);
        to_direction_input.addEventListener('change', function () { UpdateDirectionConnection(div_to, div_from); });
        to_network_input.addEventListener('change', function () { UpdateNetworkConnection(div_to, div_from); });
        to_mask_input.addEventListener('change', function () { UpdateMaskConnection(div_to, div_from); });

        UpdateNetworkConnection(div_to, div_from);
        UpdateMaskConnection(div_to, div_from);
    }
}

function Disconnect(div_from, div_to) {
    if (div_from != null) {
        CreateConnectButton(div_from, true, function () { Connect(div_from, null) })
        div_from.connected = false;

        let from_interface_input = document.getElementById(`${div_from.id}-interface_connection`);
        div_from.removeChild(from_interface_input);
        let from_device_input = document.getElementById(`${div_from.id}-device_connection`);
        div_from.removeChild(from_device_input);
        let from_check_div = document.getElementById(`${div_from.id}-extint`);
        div_from.removeChild(from_check_div);


    }

    if (div_to != null) {
        CreateConnectButton(div_to, true, function () { Connect(div_to, null) })
        div_to.connected = false;

        let to_interface_input = document.getElementById(`${div_to.id}-interface_connection`);
        div_to.removeChild(to_interface_input);
        let to_device_input = document.getElementById(`${div_to.id}-device_connection`);
        div_to.removeChild(to_device_input);
        let to_check_div = document.getElementById(`${div_to.id}-extint`);
        div_to.removeChild(to_check_div);
    }
}


function LoadCon(from_model, from_interface, to_model, to_interface, type) {
    if (from_model == to_model && from_interface == to_interface) { return; }
    let parent_div_from = document.getElementById(`model-${from_model}-interfaces`);
    let div_from;
    for (child of Array.from(parent_div_from.children)) {
        let direction_input = document.getElementById(`${child.id}-direction`);
        if (direction_input == null) { continue; }
        if (direction_input.value == from_interface) {
            div_from = child;
            break;
        }
    }

    if (div_from == null) return;
    if (!(div_from.connected)) {
        Connect(div_from, null);
        if (type.includes("ex-b")) {
            document.getElementById(`${div_from.id}-prev`).checked = true;
        } else if (type.includes("ex-f")) {
            document.getElementById(`${div_from.id}-next`).checked = true;
        } else {
            document.getElementById(`${div_from.id}-in`).checked = true;
        }
        // 
        let device_input = document.getElementById(`${div_from.id}-device_connection`);
        device_input.value = to_model;
        let interface_input = document.getElementById(`${div_from.id}-interface_connection`);
        interface_input.value = to_interface;

        let event = new Event("change");
        interface_input.dispatchEvent(event);

    }
    // if (type.includes("ex-b")) {
    //     document.getElementById(`${div_from.id}-steps`).value = type.split("-")[2];
    // }
}

function UpdateIntExt(div_from, div_to) {
    selected = "";
    if (document.getElementById(`${div_from.id}-in`).checked) { selected = 'in'; }
    else if (document.getElementById(`${div_from.id}-next`).checked) { selected = 'next'; }
    else if (document.getElementById(`${div_from.id}-prev`).checked) { selected = 'prev'; }
    switch (selected) {
        case 'in':
            //   document.getElementById(`${div_from.id}-steps_container`).style.display = "none";
            //   document.getElementById(`${div_to.id}-steps_container`).style.display = "none";
            document.getElementById(`${div_to.id}-in`).checked = true;
            break;
        case 'next':
            //    document.getElementById(`${div_from.id}-steps_container`).style.display = "none";
            //    document.getElementById(`${div_to.id}-steps_container`).style.display = "inline";
            document.getElementById(`${div_to.id}-prev`).checked = true;
            break;
        case 'prev':
            //    document.getElementById(`${div_from.id}-steps_container`).style.display = "inline";
            //    document.getElementById(`${div_to.id}-steps_container`).style.display = "none";
            document.getElementById(`${div_to.id}-next`).checked = true;
            break;
    }
}

///////////////////////////////////////////////////////////////////////////
// Update Functions
//////////////////////////////////////////////////////////////////////////
function UpdateDirectionConnection(div_from, div_to) {

    if (div_from.connected && div_to.connected) {
        interface_input = document.getElementById(`${div_to.id}-interface_connection`);
        direction_input = document.getElementById(`${div_from.id}-direction`);
        interface_input.value = direction_input.value;
    }

}

function UpdateNetworkConnection(div_from, div_to) {
    if (div_from.connected && div_to.connected) {
        from_network_input = document.getElementById(`${div_from.id}-network`);
        to_network_input = document.getElementById(`${div_to.id}-network`);
        to_network_input.value = from_network_input.value;
    }
}
function UpdateMaskConnection(div_from, div_to) {
    if (div_from.connected && div_to.connected) {
        from_mask_input = document.getElementById(`${div_from.id}-mask`);
        to_mask_input = document.getElementById(`${div_to.id}-mask`);
        to_mask_input.value = from_mask_input.value;
    }
}

function UpdateNetOptions(network_input, network_container) {
    temp = network_input.value;
    network_input.innerHTML = "";
    for (let i in Array.from(network_container.children)) {
        let option = document.createElement("option");
        option.value = i;
        if (i == temp) { option.selected = true; }
        option.textContent = i;
        option.id = `${network_input.id}-${i}`;
        network_input.appendChild(option);
    }
}




//////////////////////////////////////////////////////////////////////////////
// Create Helpers
/////////////////////////////////////////////////////////////////////////////

function AttachRadioButtonConnectionEvents(div_from, div_to, check_id) {
    let from_in_check = document.getElementById(check_id);
    from_in_check.addEventListener("change", function () { UpdateIntExt(div_from, div_to) });
}


////////////////////////////////////////////////////////////////////
// Error Checking
///////////////////////////////////////////////////////////////////
function CheckErrorRouterMapping(context, router_input) {
    router_input.style.backgroundColor = context[router_input.value] == null ? "red" : "white"
    router_input.title = context[router_input.value] == null ? "This device does not exist on the physical topology" : "";
}

function CheckErrorModelMapping(model_container, model_mapping_container) {
    for (let i in Array.from(model_mapping_container.children)) {
        let map_input = document.getElementById(`${model_mapping_container.id}-${i}-model`);
        let missing = !Array.from(model_container.children).some(child => child.id === `model-${map_input.value}`);
        map_input.style.backgroundColor = missing ? "red" : "white";
        map_input.title = missing ? "You have not made this Model yet, see Add Model" : "";
    }
}

function CheckErrorModelName(name, container) {
    for (let child of Array.from(container.children)) {
        if (child.id == name) {
            return true;
        }
    }
    return false;
}

function CheckErrorRoutingName(name, container) {
    for (let child of Array.from(container.children)) {
        if (child.id == `routing-${name}`) {
            return true;
        }
    }
    return false;
}

function CheckErrorComponentName(name, container) {
    for (let child of Array.from(container.children)) {
        if (child.id == `component-${name}`) {
            return true;
        }
    }
    return false;
}

function CheckErrorRouting() {
    // Check that there is a routing for each routing title
    for (let model of Array.from(document.getElementById("model").children)) {
        let routing_titles = document.getElementById(`${model.id}-routing`);
        for (title of Array.from(routing_titles.children)) {
            titleinput = document.getElementById(`${title.id}-title`)
            let exists = Array.from(document.getElementById("routing").children).some(routing => {
                return routing.id == `routing-${titleinput.value}`;
            });
            titleinput.style.backgroundColor = exists ? "white" : "red";
            titleinput.title = exists ? "" : "You have not made this routing yet, see Add Routing";
        }
    }

    // Check that there is a routing title for each routing;
    for (let routing of Array.from(document.getElementById("routing").children)) {
        let exists = Array.from(document.getElementById("model").children).some(model => {
            return Array.from(document.getElementById(`${model.id}-routing`).children).some(title => {
                titleinput = document.getElementById(`${title.id}-title`)
                return routing.id == `routing-${titleinput.value}`;
            });
        });
        routing.firstChild.style.backgroundColor = exists ? "white" : "red";
        routing.title = exists ? "" : "This Routing has not been attached to a model yet, see the routing list in each model";
    }


}

function CheckErrorComponent() {
    for (let model of Array.from(document.getElementById("model").children)) {
        let component_titles = document.getElementById(`${model.id}-components`);
        for (title of Array.from(component_titles.children)) {
            titleinput = document.getElementById(`${title.id}-title`);
            let exists = Array.from(document.getElementById("component").children).some(component => {
                return component.id == `component-${titleinput.value}`;
            });
            titleinput.style.backgroundColor = exists ? "white" : "red";
            titleinput.title = exists ? "" : "You have not made this Component yet, see Add Component";
        }
    }

    // Check that there is a component title for each component;
    for (let component of Array.from(document.getElementById("component").children)) {
        let exists = Array.from(document.getElementById("model").children).some(model => {
            return Array.from(document.getElementById(`${model.id}-components`).children).some(title => {
                titleinput = document.getElementById(`${title.id}-title`);
                return component.id == `component-${titleinput.value}`;
            });
        });
        component.firstChild.style.backgroundColor = exists ? "white" : "red";
        component.title = exists ? "" : "This Component has not been attached to a model yet, see the component list in each model";
    }
}

function CheckErrorInterfaceValue(context) {
    for (let model of Array.from(document.getElementById("model").children)) {
        let interfaces = document.getElementById(`${model.id}-interfaces`);
        for (let interface of Array.from(interfaces.children)) {
            let dir_input = document.getElementById(`${interface.id}-direction`);
            let bad_router = "";
            let missing = false;
            for (i in Array.from(document.getElementById("mapping").children)) {
                let model_name = document.getElementById(`mapping-${i}-model`).value;
                let router_name = document.getElementById(`mapping-${i}-router`).value;
                if (`model-${model_name}` == model.id && (context[router_name] == null || context[router_name]["Interfaces"][dir_input.value] == null)) {
                    bad_router += `${router_name} `;
                    missing = true;
                }
            };

            let number = dir_input.value;
            let clash = Array.from(interfaces.children).some(child => {
                let childInput = document.getElementById(`${child.id}-direction`);
                if (childInput.id === dir_input.id) return false;
                return childInput.value === number;
            });

            dir_input.style.backgroundColor = !missing && !clash ? "white" : "red";
            let error = "";
            if (missing) {
                error += `This interface direction does not exist on ${bad_router} Check your mappings. `;
            }
            if (clash) {
                error += "This direction already exists on this model."
            }
            dir_input.title = error;
        }
    }
}