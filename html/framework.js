function CreateFieldContainer(title, container, id) {
    let field = document.createElement("fieldset");
    field.className = "field-container";
    CreateText(title, "legend", field, 'field-title');
    container.appendChild(field);
    let div = document.createElement("div");
    div.id = id;
    field.appendChild(div);
    return div;
}

function ConfigFieldset(container, item, name, removeCallback) {
    let field = document.createElement("fieldset");
    field.className = 'item-fieldset';
    field.id = `${item}-${name}`;
    field.name = `${item}[${name}]`;
    let legend = document.createElement("legend");
    let title = document.createElement("span");
    title.textContent = name;
    title.className = 'item-legend';
    CreateButton(() => { removeCallback(field) }, "-", legend, `${name}-removebutton`, 'item-remove');
    legend.appendChild(title);
    field.appendChild(legend);
    container.appendChild(field);
    return field;
}

function CreateText(text, type, container, className) {
    let title = document.createElement(type);
    title.textContent = text;
    title.className = className;
    container.appendChild(title);
    return title;
}

function CreateContainer(container, id, name) {
    let div = document.createElement("div");
    div.name = name;
    div.id = id;
    container.appendChild(div);
    return div;
}

function CreateIndexedContainer(container, title, addCallback, id, name) {
    let list_title = document.createElement("div")
    list_title.textContent = title;
    CreateButton(() => { addCallback(list_container) }, "+", list_title, `${id}-button`, 'item-add');
    container.appendChild(list_title);

    let list_container = CreateContainer(container, id, name);
    list_container.index = 0;


    return list_container;
}

function CreateButton(onClick, text, container, id, className) {
    let new_button = document.createElement("button");
    new_button.addEventListener('click', onClick)
    new_button.className = className;
    new_button.textContent = text;
    new_button.type = "button";
    new_button.id = id;
    container.appendChild(new_button);
    return new_button;
}

function CreateInput(type, container, id, name, className) {
    let input = document.createElement("input");
    input.className = className;
    input.type = type;
    input.name = name;
    input.id = id
    container.appendChild(input);
    return input;
}

function CreateLabeledInput(text, type, container, id, name, group_elem, className) {
    let div = document.createElement(group_elem);
    let label = CreateText(text, 'label', div, 'input-label');
    label.for = id;
    let input = CreateInput(type, div, id, name, className);
    container.appendChild(div);
    return input;
}

function CreateConnectButton(int_div, connecting, callback) {
    let connect_button = document.getElementById(`${int_div.id}-connect`)
    if (connect_button == null) {
        connect_button = CreateButton(callback, "-->", int_div, `${int_div.id}-connect`, 'connect-button');
    } else {
        let new_connect_button = connect_button.cloneNode(true);
        if (connecting) {
            new_connect_button.textContent = "-->";
        }
        else {
            new_connect_button.textContent = "-/>";
        }

        new_connect_button.addEventListener('click', callback)
        int_div.replaceChild(new_connect_button, connect_button);
    }
}

function CreateRadioButton(container, name, id, text, value) {
    let check_label = document.createElement("label");
    check_label.setAttribute("for", `${id}`);
    check_label.textContent = text;
    check_label.className = 'radio-label';
    let check = document.createElement("input");
    check.id = id
    check.type = "radio";
    check.name = name;
    check.value = value;
    check.className = 'radio-input';
    container.appendChild(check_label);
    container.appendChild(check);
}


function CreateSelect(label, container, id, name, className) {
    let select_label = CreateText(label, 'label', container, 'input-label');
    select_label.for = `${container.parentElement.id}-transport`;
    let select_input = document.createElement("select");
    select_input.name = name;
    select_input.id = id;
    select_input.className = className;
    container.appendChild(select_input);
    return select_input;
}

function CreateOption(text, value, select) {
    let option = document.createElement("option");
    option.textContent = text;
    option.value = value;
    option.id = `${select.id}-${value}`;
    select.appendChild(option);
    return option;
}


function CreateTextArea(label, container, id, name, className) {
    let textarea = document.createElement("textarea");
    textarea.name = name;
    textarea.id = id;
    textarea.className = className;
    let textarea_label = CreateText(label, 'label', container, 'input-label');
    textarea_label.for = id;
    container.appendChild(textarea);
    return textarea;
}