import {
  View,
  Text,
  TouchableOpacity,
  Image,
  ScrollView,
  TextInput,
} from "react-native";

// Mapping of HTML elements to React Native components
const elementMap = {
  div: View,
  p: Text,
  span: Text,
  button: TouchableOpacity,
  img: Image,
  input: TextInput,
  section: View,
  article: View,
  main: View,
  header: View,
  footer: View,
  nav: View,
  aside: View,
  ul: View,
  ol: View,
  li: View,
  form: View,
  textarea: TextInput,
  select: View,
  option: View,
  h1: Text,
  h2: Text,
  h3: Text,
  h4: Text,
  h5: Text,
  h6: Text,
};

// Function to get the React Native component for an HTML element
export const getNativeComponent = (htmlElement) => {
  const component = elementMap[htmlElement.toLowerCase()];
  return component || View; // Default to View if no mapping exists
};

// HOC to create HTML-like components
export const createHTMLElement = (elementName) => {
  const NativeComponent = getNativeComponent(elementName);

  return ({ children, style, ...props }) => (
    <NativeComponent style={style} {...props}>
      {children}
    </NativeComponent>
  );
};

// Pre-defined components for common HTML elements
export const Div = createHTMLElement("div");
export const P = createHTMLElement("p");
export const Span = createHTMLElement("span");
export const Button = createHTMLElement("button");
export const H1 = createHTMLElement("h1");
export const H2 = createHTMLElement("h2");
export const H3 = createHTMLElement("h3");
export const Section = createHTMLElement("section");
export const Article = createHTMLElement("article");

// Example usage:
/*
import { Div, P, H1 } from './htmlToNative';

const MyComponent = () => {
  return (
    <Div style={styles.container}>
      <H1>This is a heading</H1>
      <P>This is a paragraph</P>
    </Div>
  );
};
*/
