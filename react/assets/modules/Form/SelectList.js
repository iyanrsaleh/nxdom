import React from 'react';
import { View, Text, Button } from "react-native";
import { SelectList } from 'react-native-dropdown-select-list';
import MIcon  from '../Icon';
const CustomSelectList = ({ 
  setSelected, 
  data, 
  save = "key", 
  placeholder, 
  label,
  placeholderColor = "#222",
  txColor="#222",
  searchPlaceholder = "Cari...",
  containerStyle,
  boxStyles: propBoxStyles,
  ...props 
}) => {
  const defaultStyles = {
    boxStyles: {
      backgroundColor: '#ffffff00',
      borderRadius: 12,
      borderColor: '#C9D3DB',
      borderWidth: 1,
      padding: 10,
      marginBottom: 0,
    },
    inputStyles: {
      fontSize: 16,
      color: '#333',
    },
    dropdownStyles: {
      backgroundColor: '#FFFFFF',
      borderColor: '#C9D3DB',
      borderWidth: 1,
      borderRadius: 10,
      marginTop: 5,
    },
    dropdownItemStyles: {
      borderBottomColor: '#E0E0E0',
      borderBottomWidth: 1,
      padding: 10,
    },
    dropdownTextStyles: {
      color: '#333',
      fontSize: 16,
    },
    labelStyles: {
      fontSize: 18,
      fontWeight: 'bold',
      color: '#C9D3DB',
      marginBottom: 8,
    },
  };

  // Merge boxStyles dengan defaultStyles
  const mergedBoxStyles = {
    ...defaultStyles.boxStyles,
    ...(propBoxStyles || {})
  };

  // Merge containerStyle dengan default marginBottom jika tidak ada
  const mergedContainerStyle = {
    ...(containerStyle || {}),
    // Jika containerStyle tidak memiliki marginBottom, tidak perlu menambahkan default
    // karena marginBottom dikontrol oleh parent wrapper
  };

  return (
    <View style={[mergedContainerStyle]}>
    {label ? (
      <Text style={{
        marginVertical: 0,
        fontSize: 14,
        fontWeight: '600',
        color: txColor,
        marginBottom: 0,
        paddingLeft: 2
      }}>{label}</Text>
    ) : null}
       
    <SelectList
      setSelected={setSelected}
      data={data}
      save={save}
      placeholder={placeholder}
      label={label}
      searchPlaceholder={searchPlaceholder}
      boxStyles={mergedBoxStyles}
      inputStyles={{
        ...defaultStyles.inputStyles,
        color: props.selected ? '#333' : txColor,
      }}
      dropdownStyles={defaultStyles.dropdownStyles}
      dropdownItemStyles={defaultStyles.dropdownItemStyles}
      dropdownTextStyles={defaultStyles.dropdownTextStyles}
      labelStyles={defaultStyles.labelStyles}
      {...props}
    />
    </View>
  );
};

export default CustomSelectList;
