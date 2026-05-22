import React from 'react';
import {View, Text, TextInput, StyleSheet,TouchableOpacity} from 'react-native';
// import css from 'tatiye';
import Loader from './Loader';
import MIcon  from '../Icon';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
const ButtonsAction = ({
  label,
  iconName,
  Feather,
  Ion,
  size=12,
  Material,
  border,
  txColor,
  vertical,
  background,
  loading,
  onPress='',
  onFocus = () => {},
  ...props
}) => {

  const [isFocused, setIsFocused] = React.useState(false);
  const [loder, setLoading] = React.useState(loading);
   setTimeout(async () => {
      setLoading(false);
   },4000);
   if (loading) {
    height=50
   } else {
    height="auto"
   }
  return (
    <View style={{marginBottom:9,marginTop:9}}>
    <TouchableOpacity onPress={onPress}>
      <View style={{   
    flexDirection: 'row',
    alignItems: 'center',
    height:height,
    justifyContent: 'center',
    borderRadius:border,
    paddingVertical:vertical,
    borderWidth: 1,
    backgroundColor:background,
    borderColor:background}}>
    {!loading &&(
         <Text style={{
          fontSize: size,
          fontWeight: '500',
          color:txColor,
         }}> 
       {iconName && (
        <MIcon Material={iconName} size={size}  color={txColor} /> 
        )}
       {Material &&(
          <MIcon Material={Material} size={size}  color={txColor} /> 
        )}
       {Feather &&(
         <MIcon Feather={Feather} size={size} color={txColor} /> 
        )}
        {Ion &&(
          <MIcon Ion={Ion}size={size} color={txColor} style={{marginLeft:5}} /> 
        )} aaaaaaaaa{label}
       </Text>
       )}

       {loading &&(
          <Loader visible={loading} />
       )}

      </View>
      </TouchableOpacity>
    </View>
  );
};

const style = StyleSheet.create({
  btn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 30,
    paddingVertical: 2,
    paddingHorizontal: 2,
    borderWidth: 1,
  },
  label: {
    marginVertical: 5,
    fontSize: 14,
    fontWeight: '600',
    color: '#222',
    marginBottom:1,
    paddingLeft:2
  },
  inputContainer: {
 
  },


});

export default ButtonsAction;
